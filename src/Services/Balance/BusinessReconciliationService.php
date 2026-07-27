<?php

namespace App\Services\Balance;

use App\Entity\Balance\Account;
use App\Entity\Balance\BusinessPayoutAccount;
use App\Entity\Balance\BusinessReconciliation;
use App\Entity\Balance\BusinessReconciliationEvent;
use App\Entity\Balance\InvoicePayment;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\AccountRepository;
use App\Repository\Balance\BusinessPayoutAccountRepository;
use App\Repository\Balance\BusinessReconciliationEventRepository;
use App\Repository\Balance\BusinessReconciliationRepository;
use App\Repository\Balance\InvoicePaymentRepository;
use App\Services\BaseService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\Notifications\OpenWaService;
use App\Services\SystemCurrencyService;
use App\Services\SystemTaxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Conciliación de facturas con negocios: agrupa las facturas `paid` de un negocio en un rango de
 * fechas, se le envían por WhatsApp con un link único para que las apruebe (sin login), luego un
 * admin del sistema las aprueba, y finalmente se liquida (paga) el total al negocio en efectivo o
 * transferencia. Cada paso queda registrado en BusinessReconciliationEvent para el histórico.
 */
class BusinessReconciliationService extends BaseService
{
    private const PIN_TTL_MINUTES = 10;
    private const PIN_MIN_RESEND_SECONDS = 60;
    private const PIN_MAX_ATTEMPTS = 3;
    public const PIN_VERIFIED_TTL_MINUTES = 15;

    public function __construct(
        EntityManagerInterface $entityManager,
        private BusinessReconciliationRepository $reconciliationRepository,
        private BusinessReconciliationEventRepository $eventRepository,
        private InvoicePaymentRepository $invoiceRepository,
        private AccountRepository $accountRepository,
        private BalanceService $balanceService,
        private OpenWaService $openWa,
        private UrlGeneratorInterface $urlGenerator,
        private SystemCurrencyService $systemCurrencyService,
        private SystemTaxService $systemTaxService,
        private APIExchangeRate $apiExchangeRate,
        private UserPasswordHasherInterface $passwordHasher,
        private Environment $twig,
        private DocumentNumberService $documentNumberService,
        private BusinessPayoutAccountRepository $payoutAccountRepository,
    ) {
        parent::__construct($entityManager);
    }

    /**
     * Facturas que entrarían en la conciliación si se crea ahora mismo — para que el admin
     * confirme antes de asignarlas.
     *
     * @return array{invoices: InvoicePayment[], total: string, currency: ?string, taxPercent: string, taxAmount: string, netAmount: string, payoutSplitPreview: ?array}
     */
    public function preview(string $businessAccountId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $invoices = $this->invoiceRepository->findEligibleForReconciliation($businessAccountId, $periodStart, $periodEnd);

        $total = '0.00';
        $currency = null;
        foreach ($invoices as $invoice) {
            $total = bcadd($total, $invoice->getTotalAmount(), 2);
            $currency = $invoice->getCurrency();
        }

        $tax = $this->computeTax($total);

        $account = $this->accountRepository->find($businessAccountId);
        $payoutSplitPreview = ($account && $currency !== null)
            ? $this->previewPayoutSplit($account, $tax['netAmount'], $currency)
            : null;

        return [
            'invoices' => $invoices,
            'total' => $total,
            'currency' => $currency,
            'taxPercent' => $tax['taxPercent'],
            'taxAmount' => $tax['taxAmount'],
            'netAmount' => $tax['netAmount'],
            'payoutSplitPreview' => $payoutSplitPreview,
        ];
    }

    /**
     * @return array{taxPercent: string, taxAmount: string, netAmount: string}
     */
    private function computeTax(string $subtotal): array
    {
        $taxPercent = $this->systemTaxService->getTaxPercent();
        $taxAmount = (string) round((float) bcmul($subtotal, bcdiv($taxPercent, '100', 8), 8), 2);
        $netAmount = bcsub($subtotal, $taxAmount, 2);

        return ['taxPercent' => $taxPercent, 'taxAmount' => $taxAmount, 'netAmount' => $netAmount];
    }

    public function create(
        string $businessAccountId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        ?string $performedBy
    ): BusinessReconciliation {
        return $this->entityManager->wrapInTransaction(function () use ($businessAccountId, $periodStart, $periodEnd, $performedBy) {
            $businessAccount = $this->accountRepository->find($businessAccountId);
            if (!$businessAccount) {
                throw new NotFoundException('Business account not found');
            }
            if ($businessAccount->getAccountType() !== 'business') {
                throw new ValidationException('businessAccountId debe ser una cuenta de tipo business');
            }

            $invoices = $this->invoiceRepository->findEligibleForReconciliation($businessAccountId, $periodStart, $periodEnd);
            if (count($invoices) === 0) {
                throw new BusinessException('No hay facturas pagadas sin conciliar en ese rango de fechas');
            }

            $reconciliation = new BusinessReconciliation();
            $reconciliation->setBusinessAccount($businessAccount);
            $reconciliation->setReconciliationNumber($this->documentNumberService->next('reconciliation', 'CON-'));
            $reconciliation->setPeriodStart($periodStart);
            $reconciliation->setPeriodEnd($periodEnd);
            $reconciliation->setStatus('pending_business');
            $reconciliation->setApprovalToken(bin2hex(random_bytes(32)));
            $reconciliation->setTokenExpiresAt(new \DateTimeImmutable('+7 days'));
            $reconciliation->setCreatedBy($performedBy);

            $this->persist($reconciliation);
            $this->flush();

            $total = '0.00';
            $currency = null;
            $included = 0;
            foreach ($invoices as $invoice) {
                if (!$this->invoiceRepository->assignToReconciliationIfPaid($invoice->getId()->toString(), $reconciliation->getId()->toString())) {
                    continue;
                }
                // El UPDATE atómico de arriba ya movió la fila en BD; esto sincroniza el objeto en
                // memoria (que sigue en el identity map con los valores viejos) — mismo patrón que
                // InvoiceService::processPayment() tras su markStatusIfCurrent().
                $invoice->setStatus('conciliando');
                $invoice->setReconciliation($reconciliation);
                $total = bcadd($total, $invoice->getTotalAmount(), 2);
                $currency = $invoice->getCurrency();
                $included++;
            }

            if ($included === 0) {
                throw new BusinessException('Las facturas encontradas cambiaron de estado antes de poder incluirlas');
            }

            $reconciliation->setInvoiceCount($included);
            $reconciliation->setTotalAmount($total);
            $reconciliation->setCurrency($currency ?? 'USD');

            // El split de pago (si la cuenta lo tiene configurado) se fija acá, a la tasa vigente
            // en el momento de crear la conciliación, y no se vuelve a recalcular después — ni al
            // mostrarla ni al liquidarla (ver settle()). Si no hay tasa disponible todavía para
            // CURRENCY_SECUNDARY, queda sin fijar y settle() reintenta una única vez al liquidar.
            $this->applyTaxAndPayoutSplit($reconciliation);
            $this->flush();

            $this->logEvent($reconciliation, 'created', $performedBy, null, [
                'invoiceCount' => $included,
                'totalAmount' => $total,
                'periodStart' => $periodStart->format('Y-m-d'),
                'periodEnd' => $periodEnd->format('Y-m-d'),
            ]);

            return $reconciliation;
        });
    }

    /**
     * Fija la comisión del sistema (TAXES) y el split de pago, cada uno con su propia guarda para
     * no recalcular lo que ya estaba fijado: create() los fija una vez, settle() y
     * backfillPayoutSplitIfMissing() solo actúan de red de seguridad. El split se calcula sobre el
     * neto (subtotal - comisión), no sobre el subtotal — es lo que realmente se reparte al negocio.
     */
    private function applyTaxAndPayoutSplit(BusinessReconciliation $reconciliation): void
    {
        if ($reconciliation->getNetAmount() === null) {
            $tax = $this->computeTax($reconciliation->getTotalAmount());
            $reconciliation->setTaxPercent($tax['taxPercent']);
            $reconciliation->setTaxAmount($tax['taxAmount']);
            $reconciliation->setNetAmount($tax['netAmount']);
        }

        if ($reconciliation->getSettlementBaseAmount() !== null) {
            return;
        }

        try {
            $split = $this->computePayoutSplit(
                $reconciliation->getBusinessAccount(),
                $reconciliation->getNetAmount(),
                $reconciliation->getCurrency()
            );
        } catch (BusinessException $e) {
            return;
        }

        $reconciliation->setSettlementBaseCurrency($split['baseCurrency']);
        $reconciliation->setSettlementBaseAmount($split['baseAmount']);
        $reconciliation->setSettlementBasePercent($split['basePercent']);
        $reconciliation->setSettlementSecondaryCurrency($split['secondaryCurrency']);
        $reconciliation->setSettlementSecondaryAmount($split['secondaryAmount']);
        $reconciliation->setSettlementSecondaryPercent($split['secondaryPercent']);
        $reconciliation->setSettlementExchangeRate($split['exchangeRate']);
    }

    /**
     * Autocompleta el split al ver el detalle (admin o link público) si por alguna razón nunca se
     * fijó al crear la conciliación — típicamente conciliaciones creadas antes de que este split
     * existiera, o donde la cuenta configuró el split después de creada. No hace nada si ya estaba
     * fijado (no se recalcula) ni si el split sigue sin poder calcularse (sin tasa disponible).
     */
    public function backfillPayoutSplitIfMissing(BusinessReconciliation $reconciliation): void
    {
        $this->applyTaxAndPayoutSplit($reconciliation);
        $this->flush();
    }

    public function send(string $id, ?string $performedBy): BusinessReconciliation
    {
        $reconciliation = $this->getOrFail($id);
        $businessAccount = $reconciliation->getBusinessAccount();

        if (!$businessAccount->getPhone()) {
            throw new BusinessException('El negocio no tiene teléfono configurado');
        }

        $approvalUrl = $this->urlGenerator->generate(
            'reconciliation_public_show',
            ['token' => $reconciliation->getApprovalToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $lines = [];
        $lines[] = sprintf('Hola %s,', $businessAccount->getBusinessName());
        $lines[] = sprintf(
            'Se generó una conciliación de facturas del %s al %s.',
            $reconciliation->getPeriodStart()->format('d/m/Y'),
            $reconciliation->getPeriodEnd()->format('d/m/Y')
        );
        $lines[] = '';
        $lines[] = 'Facturas incluidas:';
        foreach ($reconciliation->getInvoices() as $invoice) {
            $lines[] = sprintf(
                '- %s (Ref: %s): %s %s',
                $invoice->getInvoiceNumber(),
                $invoice->getExternalRef() ?: '-',
                $invoice->getTotalAmount(),
                $invoice->getCurrency()
            );
        }
        $lines[] = '';
        $lines[] = sprintf('Subtotal: %s %s', $reconciliation->getTotalAmount(), $reconciliation->getCurrency());
        if ($reconciliation->getNetAmount() !== null) {
            $lines[] = sprintf(
                'Comisión del sistema (%s%%): %s %s',
                $reconciliation->getTaxPercent(),
                $reconciliation->getTaxAmount(),
                $reconciliation->getCurrency()
            );
            $lines[] = sprintf('Total: %s %s', $reconciliation->getNetAmount(), $reconciliation->getCurrency());
        } else {
            $lines[] = sprintf('Total: %s %s', $reconciliation->getTotalAmount(), $reconciliation->getCurrency());
        }

        if ($reconciliation->getSettlementBaseAmount() !== null) {
            $lines[] = '';
            $lines[] = sprintf(
                'Se te pagará: %s %s (%s%%) + %s %s (%s%%)',
                $reconciliation->getSettlementBaseAmount(),
                $reconciliation->getSettlementBaseCurrency(),
                $reconciliation->getSettlementBasePercent(),
                $reconciliation->getSettlementSecondaryAmount(),
                $reconciliation->getSettlementSecondaryCurrency(),
                $reconciliation->getSettlementSecondaryPercent()
            );
        }

        $lines[] = '';
        $lines[] = 'Para aprobar o rechazar esta conciliación, ingresa al siguiente enlace:';
        $lines[] = $approvalUrl;

        $this->openWa->sendMessage($businessAccount->getPhone(), implode("\n", $lines));

        $reconciliation->setSentAt(new \DateTimeImmutable());
        $this->flush();

        $this->logEvent($reconciliation, 'sent', $performedBy);

        return $reconciliation;
    }

    public function getByToken(string $token): ?BusinessReconciliation
    {
        $reconciliation = $this->reconciliationRepository->findByToken($token);
        if (!$reconciliation) {
            return null;
        }

        if ($reconciliation->getTokenExpiresAt() !== null && $reconciliation->getTokenExpiresAt() < new \DateTimeImmutable()) {
            return null;
        }

        return $reconciliation;
    }

    /**
     * Genera un PIN de un solo uso y lo envía por WhatsApp al teléfono ya registrado del negocio —
     * prueba que quien va a aprobar/rechazar tiene acceso a ese teléfono, no solo el link (que puede
     * abrirse desde cualquier dispositivo). Paso previo y separado de approveByBusiness/
     * rejectByBusiness (pensado para que una futura app móvil llame estos mismos pasos sin pasar
     * por la página web).
     */
    public function requestApprovalPin(string $token): void
    {
        $reconciliation = $this->getByToken($token);
        if (!$reconciliation) {
            throw new NotFoundException('Enlace de conciliación inválido o expirado');
        }

        if ($reconciliation->getStatus() !== 'pending_business') {
            throw new BusinessException('Esta conciliación ya fue procesada');
        }

        $lastRequested = $reconciliation->getApprovalPinRequestedAt();
        if ($lastRequested !== null && $lastRequested > new \DateTimeImmutable('-' . self::PIN_MIN_RESEND_SECONDS . ' seconds')) {
            throw new BusinessException('Espera un minuto antes de solicitar otro código');
        }

        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $reconciliation->setApprovalPinHash($this->passwordHasher->hashPassword($reconciliation, $pin));
        $reconciliation->setApprovalPinExpiresAt(new \DateTimeImmutable('+' . self::PIN_TTL_MINUTES . ' minutes'));
        $reconciliation->setApprovalPinAttempts(0);
        $reconciliation->setApprovalPinRequestedAt(new \DateTimeImmutable());
        $reconciliation->setApprovalPinVerifiedAt(null);
        $this->flush();

        try {
            $message = $this->twig->render('emails/whatsapp/reconciliation_pin.txt.twig', [
                'businessName' => $reconciliation->getBusinessAccount()->getBusinessName(),
                'pinCode' => $pin,
                'ttlMinutes' => self::PIN_TTL_MINUTES,
            ]);
            $this->openWa->sendMessage($reconciliation->getBusinessAccount()->getPhone(), $message);
        } catch (\Throwable $e) {
            throw new BusinessException('No se pudo enviar el código de verificación, intenta de nuevo');
        }

        $this->logEvent($reconciliation, 'pin_requested', null);
    }

    /**
     * Verifica el PIN enviado por requestApprovalPin(). Un solo uso: si es correcto, invalida el
     * hash (no sirve dos veces) y marca approvalPinVerifiedAt, que approveByBusiness/
     * rejectByBusiness exigen fresco (ver PIN_VERIFIED_TTL_MINUTES) antes de dejar actuar.
     */
    public function verifyApprovalPin(string $token, string $pin): void
    {
        $reconciliation = $this->getByToken($token);
        if (!$reconciliation) {
            throw new NotFoundException('Enlace de conciliación inválido o expirado');
        }

        if ($reconciliation->getApprovalPinHash() === null) {
            throw new ValidationException('Debes solicitar un código de verificación antes de continuar');
        }

        if ($reconciliation->getApprovalPinExpiresAt() < new \DateTimeImmutable()) {
            throw new ValidationException('El código de verificación expiró, solicita uno nuevo');
        }

        if ($reconciliation->getApprovalPinAttempts() >= self::PIN_MAX_ATTEMPTS) {
            throw new ValidationException('Superaste el número de intentos, solicita un código nuevo');
        }

        if (!$this->passwordHasher->isPasswordValid($reconciliation, $pin)) {
            $reconciliation->setApprovalPinAttempts($reconciliation->getApprovalPinAttempts() + 1);
            $this->flush();
            $this->logEvent($reconciliation, 'pin_verify_failed', null);

            $remaining = self::PIN_MAX_ATTEMPTS - $reconciliation->getApprovalPinAttempts();
            throw new ValidationException("Código incorrecto, te quedan {$remaining} intento(s)");
        }

        $reconciliation->setApprovalPinHash(null);
        $reconciliation->setApprovalPinExpiresAt(null);
        $reconciliation->setApprovalPinVerifiedAt(new \DateTimeImmutable());
        $this->flush();

        $this->logEvent($reconciliation, 'pin_verified', null);
    }

    private function assertPinVerified(BusinessReconciliation $reconciliation): void
    {
        $verifiedAt = $reconciliation->getApprovalPinVerifiedAt();
        if ($verifiedAt === null || $verifiedAt < new \DateTimeImmutable('-' . self::PIN_VERIFIED_TTL_MINUTES . ' minutes')) {
            throw new ValidationException('Debes verificar tu código antes de aprobar o rechazar');
        }
    }

    /**
     * @return BusinessPayoutAccount[] Cuentas activas del negocio, agrupadas por si hace falta
     * una (sin split) o dos (con split, una por moneda) para la página pública de aprobación.
     */
    public function getPayoutAccountsForApproval(BusinessReconciliation $reconciliation): array
    {
        $businessAccount = $reconciliation->getBusinessAccount();
        $hasSplit = $reconciliation->getSettlementBaseAmount() !== null;

        $baseCurrency = $hasSplit ? $reconciliation->getSettlementBaseCurrency() : $reconciliation->getCurrency();
        $accounts = [
            'hasSplit' => $hasSplit,
            'base' => [
                'currency' => $baseCurrency,
                'accounts' => $this->payoutAccountRepository->findActiveByAccountAndCurrency($businessAccount, $baseCurrency),
            ],
            'secondary' => null,
        ];

        if ($hasSplit) {
            $secondaryCurrency = $reconciliation->getSettlementSecondaryCurrency();
            $accounts['secondary'] = [
                'currency' => $secondaryCurrency,
                'accounts' => $this->payoutAccountRepository->findActiveByAccountAndCurrency($businessAccount, $secondaryCurrency),
            ];
        }

        return $accounts;
    }

    public function approveByBusiness(
        string $token,
        string $approverName,
        ?string $payoutAccountBaseId,
        ?string $payoutAccountSecondaryId = null
    ): BusinessReconciliation {
        $reconciliation = $this->getByToken($token);
        if (!$reconciliation) {
            throw new NotFoundException('Enlace de conciliación inválido o expirado');
        }

        $this->assertPinVerified($reconciliation);

        $businessAccount = $reconciliation->getBusinessAccount();
        $hasSplit = $reconciliation->getSettlementBaseAmount() !== null;

        if (!$payoutAccountBaseId) {
            throw new ValidationException('Debes elegir la cuenta a la que se te debe realizar el pago');
        }
        $payoutAccountBase = $this->resolvePayoutAccountForApproval(
            $payoutAccountBaseId,
            $businessAccount,
            $hasSplit ? $reconciliation->getSettlementBaseCurrency() : $reconciliation->getCurrency()
        );

        $payoutAccountSecondary = null;
        if ($hasSplit) {
            if (!$payoutAccountSecondaryId) {
                throw new ValidationException('Debes elegir la cuenta para la segunda moneda del pago');
            }
            $payoutAccountSecondary = $this->resolvePayoutAccountForApproval(
                $payoutAccountSecondaryId,
                $businessAccount,
                $reconciliation->getSettlementSecondaryCurrency()
            );
        }

        if (!$this->reconciliationRepository->markStatusIfCurrent($reconciliation->getId()->toString(), 'pending_business', 'approved_business')) {
            throw new BusinessException('Esta conciliación ya fue procesada');
        }

        $reconciliation->setStatus('approved_business');
        $reconciliation->setBusinessApprovedAt(new \DateTimeImmutable());
        $reconciliation->setBusinessApprovedBy($approverName);
        $reconciliation->setPayoutAccountBase($payoutAccountBase);
        $reconciliation->setPayoutAccountSecondary($payoutAccountSecondary);
        $this->flush();

        $this->logEvent($reconciliation, 'approved_business', $approverName, null, [
            'payoutAccountBase' => $payoutAccountBase->getAlias(),
            'payoutAccountSecondary' => $payoutAccountSecondary?->getAlias(),
        ]);

        return $reconciliation;
    }

    private function resolvePayoutAccountForApproval(string $id, Account $businessAccount, ?string $expectedCurrency): BusinessPayoutAccount
    {
        $payoutAccount = $this->payoutAccountRepository->find($id);
        if (!$payoutAccount || $payoutAccount->getAccount()?->getId()?->toString() !== $businessAccount->getId()->toString()) {
            throw new ValidationException('Cuenta de pago inválida');
        }
        if (!$payoutAccount->isActive()) {
            throw new ValidationException('Esa cuenta de pago está inactiva');
        }
        if ($expectedCurrency !== null && $payoutAccount->getCurrency() !== $expectedCurrency) {
            throw new ValidationException("La cuenta elegida debe estar en {$expectedCurrency}");
        }

        return $payoutAccount;
    }

    public function rejectByBusiness(string $token, string $approverName, ?string $reason): BusinessReconciliation
    {
        $reconciliation = $this->getByToken($token);
        if (!$reconciliation) {
            throw new NotFoundException('Enlace de conciliación inválido o expirado');
        }

        $this->assertPinVerified($reconciliation);

        if (!$this->reconciliationRepository->markStatusIfCurrent($reconciliation->getId()->toString(), 'pending_business', 'rejected_business')) {
            throw new BusinessException('Esta conciliación ya fue procesada');
        }

        $reconciliation->setStatus('rejected_business');
        $reconciliation->setRejectedAt(new \DateTimeImmutable());
        $reconciliation->setRejectedBy($approverName);
        $reconciliation->setRejectionReason($reason);
        $this->releaseInvoices($reconciliation);
        $this->flush();

        $this->logEvent($reconciliation, 'rejected_business', $approverName, $reason);

        return $reconciliation;
    }

    public function approveByAdmin(string $id, ?string $performedBy): BusinessReconciliation
    {
        $reconciliation = $this->getOrFail($id);

        if (!$this->reconciliationRepository->markStatusIfCurrent($id, 'approved_business', 'approved_admin')) {
            throw new BusinessException('La conciliación debe estar aprobada por el negocio antes de aprobarla como administrador');
        }

        $reconciliation->setStatus('approved_admin');
        $reconciliation->setAdminApprovedAt(new \DateTimeImmutable());
        $reconciliation->setAdminApprovedBy($performedBy);
        $this->flush();

        $this->logEvent($reconciliation, 'approved_admin', $performedBy);

        return $reconciliation;
    }

    public function rejectByAdmin(string $id, ?string $performedBy, ?string $reason): BusinessReconciliation
    {
        $reconciliation = $this->getOrFail($id);
        $currentStatus = $reconciliation->getStatus();

        if (!in_array($currentStatus, ['pending_business', 'approved_business'], true)
            || !$this->reconciliationRepository->markStatusIfCurrent($id, $currentStatus, 'rejected_admin')) {
            throw new BusinessException('Esta conciliación no se puede rechazar en su estado actual');
        }

        $reconciliation->setStatus('rejected_admin');
        $reconciliation->setRejectedAt(new \DateTimeImmutable());
        $reconciliation->setRejectedBy($performedBy);
        $reconciliation->setRejectionReason($reason);
        $this->releaseInvoices($reconciliation);
        $this->flush();

        $this->logEvent($reconciliation, 'rejected_admin', $performedBy, $reason);

        return $reconciliation;
    }

    public function settle(
        string $id,
        string $method,
        ?string $reference,
        ?string $secondaryMethod,
        ?string $secondaryReference,
        ?string $notes,
        ?string $performedBy
    ): BusinessReconciliation {
        $this->validateSettlementMethod($method, $reference, 'settlementMethod', 'settlementReference');

        // Todo lo validable (formato de los métodos, disponibilidad de tasa) se resuelve acá, antes
        // de abrir la transacción — una excepción de negocio/validación dentro de wrapInTransaction()
        // deja el EntityManager cerrado, así que no debe usarse para validar, solo para mutar.
        $reconciliation = $this->getOrFail($id);
        $this->applyTaxAndPayoutSplit($reconciliation);

        $account = $reconciliation->getBusinessAccount();
        $hasSplit = $account->getPayoutCurrencyPercent() !== null && $account->getPayoutSecondaryCurrencyPercent() !== null;

        if ($hasSplit && $reconciliation->getSettlementBaseAmount() === null) {
            throw new BusinessException(
                'No hay tasa de cambio configurada para completar el split de pago de esta conciliación; configúrala antes de liquidar'
            );
        }

        // Si hay split, cada moneda se paga (y se registra) por separado — ej. la parte en EUR
        // por transferencia y la parte en CUP en efectivo, cada una con su propia referencia.
        if ($hasSplit) {
            $this->validateSettlementMethod($secondaryMethod ?? '', $secondaryReference, 'settlementSecondaryMethod', 'settlementSecondaryReference');
        }

        return $this->entityManager->wrapInTransaction(function () use ($id, $reconciliation, $method, $reference, $secondaryMethod, $secondaryReference, $notes, $performedBy, $hasSplit) {
            if (!$this->reconciliationRepository->markStatusIfCurrent($id, 'approved_admin', 'settled')) {
                throw new BusinessException('La conciliación debe estar aprobada por el administrador antes de liquidarla');
            }

            $this->balanceService->deductBalance(
                accountId: $reconciliation->getBusinessAccount()->getId()->toString(),
                amount: $reconciliation->getTotalAmount(),
                currency: $reconciliation->getCurrency(),
                type: 'reconciliation_settlement',
                referenceType: 'business_reconciliation',
                referenceId: $id,
                description: 'Liquidación de conciliación',
                performedBy: $performedBy
            );

            foreach ($reconciliation->getInvoices() as $invoice) {
                $invoice->setStatus('conciliada');
            }

            $reconciliation->setStatus('settled');
            $reconciliation->setSettledAt(new \DateTimeImmutable());
            $reconciliation->setSettledBy($performedBy);
            $reconciliation->setSettlementMethod($method);
            $reconciliation->setSettlementReference($reference);
            if ($hasSplit) {
                $reconciliation->setSettlementSecondaryMethod($secondaryMethod);
                $reconciliation->setSettlementSecondaryReference($secondaryReference);
            }
            $reconciliation->setSettlementNotes($notes);
            $this->flush();

            $this->logEvent($reconciliation, 'settled', $performedBy, $notes, [
                'settlementMethod' => $method,
                'settlementReference' => $reference,
                'settlementSecondaryMethod' => $reconciliation->getSettlementSecondaryMethod(),
                'settlementSecondaryReference' => $reconciliation->getSettlementSecondaryReference(),
                'totalAmount' => $reconciliation->getTotalAmount(),
                'currency' => $reconciliation->getCurrency(),
                'settlementBaseAmount' => $reconciliation->getSettlementBaseAmount(),
                'settlementSecondaryAmount' => $reconciliation->getSettlementSecondaryAmount(),
                'settlementExchangeRate' => $reconciliation->getSettlementExchangeRate(),
            ]);

            return $reconciliation;
        });
    }

    private function validateSettlementMethod(string $method, ?string $reference, string $methodField, string $referenceField): void
    {
        if (!in_array($method, ['efectivo', 'transferencia'], true)) {
            throw new ValidationException("{$methodField} debe ser efectivo o transferencia");
        }
        if ($method === 'transferencia' && !$reference) {
            throw new ValidationException("{$referenceField} es requerido para transferencia");
        }
    }

    /**
     * Igual que computePayoutSplit() pero para mostrarlo antes de liquidar (vista previa al crear,
     * lista/detalle admin, página pública) — si no hay tasa configurada no revienta la página,
     * devuelve el motivo en 'error' para que la UI lo muestre como advertencia en vez de números.
     *
     * @return array{baseCurrency: ?string, baseAmount: ?string, basePercent: ?string, secondaryCurrency: ?string, secondaryAmount: ?string, secondaryPercent: ?string, exchangeRate: ?string, error: ?string}
     */
    public function previewPayoutSplit(Account $account, string $amount, string $currency): array
    {
        try {
            return $this->computePayoutSplit($account, $amount, $currency) + ['error' => null];
        } catch (BusinessException $e) {
            return [
                'baseCurrency' => null,
                'baseAmount' => null,
                'basePercent' => null,
                'secondaryCurrency' => null,
                'secondaryAmount' => null,
                'secondaryPercent' => null,
                'exchangeRate' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reparte un monto (de la cuenta de negocio $account, en $currency — normalmente el neto ya sin
     * la comisión del sistema) entre la moneda base del sistema y CURRENCY_SECUNDARY según los
     * porcentajes configurados en la cuenta (opcionales — si no están ambos configurados, no hay
     * split y se mantiene el comportamiento anterior de pagar el 100% en $currency). Recibe
     * primitivos en vez de un BusinessReconciliation para poder reutilizarse tanto en una
     * conciliación ya creada como en la vista previa antes de crearla.
     *
     * @return array{baseCurrency: ?string, baseAmount: ?string, basePercent: ?string, secondaryCurrency: ?string, secondaryAmount: ?string, secondaryPercent: ?string, exchangeRate: ?string}
     */
    private function computePayoutSplit(Account $account, string $amount, string $currency): array
    {
        $noSplit = ['baseCurrency' => null, 'baseAmount' => null, 'basePercent' => null, 'secondaryCurrency' => null, 'secondaryAmount' => null, 'secondaryPercent' => null, 'exchangeRate' => null];

        $currencyPercent = $account->getPayoutCurrencyPercent();
        $secondaryPercent = $account->getPayoutSecondaryCurrencyPercent();

        if ($currencyPercent === null || $secondaryPercent === null) {
            return $noSplit;
        }

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $secondaryCurrency = $this->systemCurrencyService->getSecondaryCurrency();

        // El monto puede estar registrado en cualquier moneda, no necesariamente la base del
        // sistema — convertToBase() ya hace no-op si coinciden.
        $conversion = $this->apiExchangeRate->convertToBase($amount, $currency);
        $amountInBase = $conversion['convertedAmount'];

        $baseAmount = (string) round((float) bcmul($amountInBase, bcdiv($currencyPercent, '100', 8), 8), 2);

        if ($secondaryCurrency === '' || $secondaryCurrency === $baseCurrency) {
            // Sin conversión de por medio (misma moneda): por resta, para que ambos montos sumen
            // exacto el monto repartido sin descuadrar un centavo por redondeos independientes.
            $secondaryAmountInBase = bcsub($amountInBase, $baseAmount, 2);

            return [
                'baseCurrency' => $baseCurrency,
                'baseAmount' => $baseAmount,
                'basePercent' => $currencyPercent,
                'secondaryCurrency' => $secondaryCurrency !== '' ? $secondaryCurrency : $baseCurrency,
                'secondaryAmount' => $secondaryAmountInBase,
                'secondaryPercent' => $secondaryPercent,
                'exchangeRate' => '1.0000',
            ];
        }

        // getRate()/convertToBase() (no convert()) porque leen la tabla ExchangeRate, donde vive
        // la tasa manual de CURRENCY_SECUNDARY (ej. CUP) — convert() lee el snapshot de la API
        // externa, que nunca tendría una moneda de tasa manual.
        $rate = $this->apiExchangeRate->getRate($secondaryCurrency);
        if ($rate === null) {
            throw new BusinessException(
                "No hay tasa de cambio configurada para {$secondaryCurrency}; no se puede liquidar el split de pago de esta conciliación"
            );
        }

        // Con conversión de por medio: se calcula con su propio porcentaje del monto (no por resta
        // del tramo base) y a precisión completa, sin redondear a centavos en la moneda base
        // todavía — redondear acá antes de multiplicar por una tasa como 800 amplifica el error de
        // redondeo (ej. 0.002 EUR de más se convierten en 1.6 CUP de más).
        $secondaryAmountInBaseFullPrecision = bcmul($amountInBase, bcdiv($secondaryPercent, '100', 8), 8);
        $secondaryAmount = (string) round((float) bcmul($secondaryAmountInBaseFullPrecision, (string) $rate, 8), 2);

        return [
            'baseCurrency' => $baseCurrency,
            'baseAmount' => $baseAmount,
            'basePercent' => $currencyPercent,
            'secondaryCurrency' => $secondaryCurrency,
            'secondaryAmount' => $secondaryAmount,
            'secondaryPercent' => $secondaryPercent,
            'exchangeRate' => (string) $rate,
        ];
    }

    public function get(string $id): ?BusinessReconciliation
    {
        return $this->reconciliationRepository->find($id);
    }

    /**
     * @return BusinessReconciliationEvent[]
     */
    public function getEvents(string $id): array
    {
        return $this->eventRepository->findByReconciliation($id);
    }

    public function list(array $filters = []): array
    {
        $qb = $this->reconciliationRepository->createQueryBuilder('r');

        if (isset($filters['businessAccountId'])) {
            $qb->andWhere('r.businessAccount = :businessAccountId')
               ->setParameter('businessAccountId', $filters['businessAccountId']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $filters['status']);
        }

        $qb->orderBy('r.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Reporte de conciliaciones agrupado por negocio, con el desglose de cada una (subtotal,
     * comisión del sistema, monto pagado en CURRENCY/CURRENCY_SECUNDARY) y el equivalente de esos
     * dos montos en la moneda que cada negocio tiene configurada (Account::defaultCurrency) — para
     * consumo tanto de la vista previa en pantalla como del PDF. Sin $status trae cualquier estado.
     *
     * @return array{periodStart: string, periodEnd: string, baseCurrency: string, secondaryCurrency: string, businesses: array, grandTotal: array}
     */
    public function buildReconciliationReport(\DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?string $status = null, ?string $businessAccountId = null): array
    {
        $reconciliations = $this->reconciliationRepository->findForReport($periodStart, $periodEnd, $status, $businessAccountId);

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $secondaryCurrency = $this->systemCurrencyService->getSecondaryCurrency();

        $businesses = [];
        $grandTotal = ['subtotal' => '0.00', 'taxAmount' => '0.00', 'baseAmount' => '0.00', 'secondaryAmount' => '0.00'];

        foreach ($reconciliations as $r) {
            $account = $r->getBusinessAccount();
            $accId = $account->getId()->toString();
            $defaultCurrency = $account->getDefaultCurrency();

            if (!isset($businesses[$accId])) {
                $businesses[$accId] = [
                    'businessName' => $account->getBusinessName(),
                    'defaultCurrency' => $defaultCurrency,
                    'rows' => [],
                    'accounts' => [],
                    'subtotalTotal' => '0.00',
                    'taxTotal' => '0.00',
                    'baseAmountTotal' => '0.00',
                    'secondaryAmountTotal' => '0.00',
                    'defaultCurrencyBaseTotal' => '0.00',
                    'defaultCurrencySecondaryTotal' => '0.00',
                ];
            }

            $baseAmount = $r->getSettlementBaseAmount();
            $secondaryAmount = $r->getSettlementSecondaryAmount();
            $baseInDefault = $this->convertAmount($baseAmount, $baseCurrency, $defaultCurrency);
            $secondaryInDefault = $this->convertAmount($secondaryAmount, $secondaryCurrency, $defaultCurrency);

            $businesses[$accId]['rows'][] = [
                'reconciliationNumber' => $r->getReconciliationNumber(),
                'status' => $r->getStatus(),
                'periodStart' => $r->getPeriodStart()->format('Y-m-d'),
                'periodEnd' => $r->getPeriodEnd()->format('Y-m-d'),
                'currency' => $r->getCurrency(),
                'subtotal' => $r->getTotalAmount(),
                'taxPercent' => $r->getTaxPercent(),
                'taxAmount' => $r->getTaxAmount(),
                'netAmount' => $r->getNetAmount(),
                'baseCurrency' => $r->getSettlementBaseCurrency(),
                'baseAmount' => $baseAmount,
                'basePercent' => $r->getSettlementBasePercent(),
                'baseAmountInDefaultCurrency' => $baseInDefault,
                'secondaryCurrency' => $r->getSettlementSecondaryCurrency(),
                'secondaryAmount' => $secondaryAmount,
                'secondaryPercent' => $r->getSettlementSecondaryPercent(),
                'secondaryAmountInDefaultCurrency' => $secondaryInDefault,
                'exchangeRate' => $r->getSettlementExchangeRate(),
                'settledAt' => $r->getSettledAt()?->format('Y-m-d'),
            ];

            // Subtotal/comisión se suman en la moneda propia de las facturas de ese negocio (en la
            // práctica siempre la misma dentro de un mismo negocio). Base/secundaria ya son
            // uniformes en todo el sistema (siempre CURRENCY/CURRENCY_SECUNDARY), se suman directo.
            $businesses[$accId]['subtotalTotal'] = bcadd($businesses[$accId]['subtotalTotal'], $r->getTotalAmount(), 2);
            $businesses[$accId]['taxTotal'] = bcadd($businesses[$accId]['taxTotal'], $r->getTaxAmount() ?? '0', 2);
            $businesses[$accId]['baseAmountTotal'] = bcadd($businesses[$accId]['baseAmountTotal'], $baseAmount ?? '0', 2);
            $businesses[$accId]['secondaryAmountTotal'] = bcadd($businesses[$accId]['secondaryAmountTotal'], $secondaryAmount ?? '0', 2);
            $businesses[$accId]['defaultCurrencyBaseTotal'] = bcadd($businesses[$accId]['defaultCurrencyBaseTotal'], $baseInDefault ?? '0', 2);
            $businesses[$accId]['defaultCurrencySecondaryTotal'] = bcadd($businesses[$accId]['defaultCurrencySecondaryTotal'], $secondaryInDefault ?? '0', 2);

            // Desglose por cuenta real de pago (BusinessPayoutAccount) — a cuál cuenta hay que
            // transferirle qué, para negocios con varias cuentas registradas. Una conciliación con
            // split aporta a dos cuentas (una por moneda); sin split, a una sola en su moneda propia.
            // "unassigned" agrupa conciliaciones que aún no pasaron por la aprobación del negocio
            // (approveByBusiness), que es cuando se fija la cuenta.
            if ($baseAmount !== null) {
                $this->addAccountContribution($businesses[$accId]['accounts'], $r->getPayoutAccountBase(), $r->getSettlementBaseCurrency(), $baseAmount);
                $this->addAccountContribution($businesses[$accId]['accounts'], $r->getPayoutAccountSecondary(), $r->getSettlementSecondaryCurrency(), $secondaryAmount);
            } else {
                $this->addAccountContribution($businesses[$accId]['accounts'], $r->getPayoutAccountBase(), $r->getCurrency(), $r->getNetAmount());
            }

            // El gran total no incluye el "equivalente en la moneda del negocio" — mezclar montos
            // de negocios con defaultCurrency distinta en una sola cifra no tendría sentido.
            $grandTotal['subtotal'] = bcadd($grandTotal['subtotal'], $r->getTotalAmount(), 2);
            $grandTotal['taxAmount'] = bcadd($grandTotal['taxAmount'], $r->getTaxAmount() ?? '0', 2);
            $grandTotal['baseAmount'] = bcadd($grandTotal['baseAmount'], $baseAmount ?? '0', 2);
            $grandTotal['secondaryAmount'] = bcadd($grandTotal['secondaryAmount'], $secondaryAmount ?? '0', 2);
        }

        foreach ($businesses as &$business) {
            $business['netTotal'] = bcsub($business['subtotalTotal'], $business['taxTotal'], 2);
            $business['accounts'] = array_values($business['accounts']);
        }
        unset($business);

        return [
            'periodStart' => $periodStart->format('Y-m-d'),
            'periodEnd' => $periodEnd->format('Y-m-d'),
            'baseCurrency' => $baseCurrency,
            'secondaryCurrency' => $secondaryCurrency,
            'businesses' => array_values($businesses),
            'grandTotal' => $grandTotal,
        ];
    }

    /**
     * Suma la contribución de una conciliación al total de una cuenta real de pago dentro del
     * desglose por cuenta de buildReconciliationReport(). $accounts se pasa por referencia y se
     * indexa por el id de la cuenta (o 'unassigned' si el negocio aún no la eligió al aprobar).
     */
    private function addAccountContribution(array &$accounts, ?BusinessPayoutAccount $payoutAccount, ?string $currency, ?string $amount): void
    {
        if ($amount === null) {
            return;
        }

        // Sin cuenta asignada se agrupa por moneda (y no solo bajo 'unassigned' a secas) para no
        // mezclar en un mismo total montos de base y secundaria cuando ninguna tiene cuenta aún.
        $key = $payoutAccount?->getId()?->toString() ?? 'unassigned:' . $currency;
        if (!isset($accounts[$key])) {
            $accounts[$key] = [
                'payoutAccountId' => $payoutAccount?->getId(),
                'alias' => $payoutAccount?->getAlias(),
                'currency' => $currency,
                'accountNumber' => $payoutAccount?->getAccountNumber(),
                'bankName' => $payoutAccount?->getBankName(),
                'amountTotal' => '0.00',
                'reconciliationCount' => 0,
            ];
        }

        $accounts[$key]['amountTotal'] = bcadd($accounts[$key]['amountTotal'], $amount, 2);
        $accounts[$key]['reconciliationCount']++;
    }

    /**
     * Convierte un monto entre dos monedas cualquiera, pasando por la base del sistema —
     * convertToBase()/getRate() son las mismas usadas en computePayoutSplit(), respaldadas por la
     * tabla ExchangeRate (soporta tasas manuales como CUP). Devuelve null si no hay tasa disponible.
     */
    private function convertAmount(?string $amount, string $fromCurrency, string $toCurrency): ?string
    {
        return $this->apiExchangeRate->convertBetween($amount, $fromCurrency, $toCurrency);
    }

    /**
     * Libera las facturas de una conciliación rechazada: vuelven a 'paid' y quedan disponibles
     * para una futura conciliación.
     */
    private function releaseInvoices(BusinessReconciliation $reconciliation): void
    {
        foreach ($reconciliation->getInvoices() as $invoice) {
            $invoice->setStatus('paid');
            $invoice->setReconciliation(null);
        }
    }

    private function getOrFail(string $id): BusinessReconciliation
    {
        $reconciliation = $this->reconciliationRepository->find($id);
        if (!$reconciliation) {
            throw new NotFoundException('Reconciliation not found');
        }

        return $reconciliation;
    }

    private function logEvent(
        BusinessReconciliation $reconciliation,
        string $eventType,
        ?string $performedBy,
        ?string $notes = null,
        ?array $metadata = null
    ): void {
        $event = new BusinessReconciliationEvent();
        $event->setReconciliation($reconciliation);
        $event->setEventType($eventType);
        $event->setPerformedBy($performedBy);
        $event->setNotes($notes);
        $event->setMetadata($metadata);

        $this->persist($event);
        $this->flush();
    }
}
