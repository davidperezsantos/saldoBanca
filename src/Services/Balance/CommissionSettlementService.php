<?php

namespace App\Services\Balance;

use App\Entity\Balance\CommissionSettlement;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\BusinessReconciliationRepository;
use App\Repository\Balance\CommissionSettlementRepository;
use App\Repository\UserRepository;
use App\Services\BaseService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\Notifications\OpenWaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

/**
 * Liquidación de la comisión del sistema entre el Administrador (dueño de la cuenta física con el
 * dinero) y el Super Administrador (a quien corresponde esa comisión) — ver docblock de
 * CommissionSettlement para el flujo completo de estados.
 */
class CommissionSettlementService extends BaseService
{
    private const PIN_TTL_MINUTES = 10;
    private const PIN_MIN_RESEND_SECONDS = 60;
    private const PIN_MAX_ATTEMPTS = 3;
    public const PIN_VERIFIED_TTL_MINUTES = 15;

    public function __construct(
        EntityManagerInterface $entityManager,
        private CommissionSettlementRepository $settlementRepository,
        private BusinessReconciliationRepository $reconciliationRepository,
        private DocumentNumberService $documentNumberService,
        private APIExchangeRate $apiExchangeRate,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private OpenWaService $openWa,
        private Environment $twig,
    ) {
        parent::__construct($entityManager);
    }

    /**
     * Comisión disponible para liquidar, expresada en $targetCurrency. Un negocio puede facturar
     * en cualquier moneda, así que la comisión retenida puede existir en varias monedas a la vez:
     * para cada una se calcula lo ganado (conciliaciones liquidadas) menos lo ya reservado por
     * cualquier liquidación de comisión existente (en cualquier estado — se reserva desde que se
     * crea, no recién al cerrarse, para no comprometer el mismo dinero dos veces), y ese neto —
     * todavía en su moneda original — se convierte recién al final a $targetCurrency con la tasa
     * vigente, para no perder precisión mezclando conversiones a mitad de camino.
     *
     * Si no hay tasa disponible para convertir alguna moneda, esa porción se omite del total en
     * vez de hacer fallar toda la consulta.
     */
    public function getAvailableCommission(string $targetCurrency): string
    {
        $targetCurrency = strtoupper(trim($targetCurrency));
        $earnedByCurrency = $this->reconciliationRepository->sumSettledTaxAmountByCurrency();
        $reservedByCurrency = $this->settlementRepository->sumReservedAmountByCurrency();

        $currencies = array_unique(array_merge(array_keys($earnedByCurrency), array_keys($reservedByCurrency)));

        $total = '0.00';
        foreach ($currencies as $currency) {
            $earned = $earnedByCurrency[$currency] ?? '0.00';
            $reserved = $reservedByCurrency[$currency] ?? '0.00';
            $net = bcsub($earned, $reserved, 2);

            if (bccomp($net, '0', 2) === 0) {
                continue;
            }

            $converted = $this->apiExchangeRate->convertBetween($net, $currency, $targetCurrency);
            if ($converted === null) {
                continue;
            }

            $total = bcadd($total, $converted, 2);
        }

        return $total;
    }

    public function create(string $amount, string $currency, ?string $notes, string $createdBy): CommissionSettlement
    {
        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            throw new ValidationException('La moneda debe ser un código de 3 letras');
        }
        if (bccomp($amount, '0', 2) <= 0) {
            throw new ValidationException('El monto debe ser mayor que cero');
        }

        $available = $this->getAvailableCommission($currency);
        if (bccomp($amount, $available, 2) > 0) {
            throw new BusinessException("El monto supera la comisión disponible en {$currency} ({$available})");
        }

        $settlement = new CommissionSettlement();
        $settlement->setAmount($amount);
        $settlement->setCurrency($currency);
        $settlement->setNotes($notes ?: null);
        $settlement->setCreatedBy($createdBy);
        $settlement->setSettlementNumber($this->documentNumberService->next('commission_settlement', 'COM-'));

        $this->persist($settlement);
        $this->flush();

        return $settlement;
    }

    public function approveByAdmin(string $id, string $approvedBy): CommissionSettlement
    {
        $settlement = $this->getOrFail($id);

        if (!$this->settlementRepository->markStatusIfCurrent($id, 'pending_admin_approval', 'approved_admin')) {
            throw new BusinessException('Esta liquidación ya fue procesada');
        }

        $settlement->setStatus('approved_admin');
        $settlement->setAdminApprovedAt(new \DateTimeImmutable());
        $settlement->setAdminApprovedBy($approvedBy);
        $this->flush();

        return $settlement;
    }

    public function assignPayoutAccount(string $id, string $accountNumber, ?string $bankName, ?string $accountHolder, string $assignedBy): CommissionSettlement
    {
        $settlement = $this->getOrFail($id);

        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            throw new ValidationException('El número de cuenta es requerido');
        }

        if (!$this->settlementRepository->markStatusIfCurrent($id, 'approved_admin', 'pending_settlement')) {
            throw new BusinessException('Esta liquidación no está lista para asignarle una cuenta');
        }

        $settlement->setStatus('pending_settlement');
        $settlement->setPayoutAccountNumber($accountNumber);
        $settlement->setPayoutBankName($bankName ?: null);
        $settlement->setPayoutAccountHolder($accountHolder ?: null);
        $settlement->setAccountAssignedAt(new \DateTimeImmutable());
        $settlement->setAccountAssignedBy($assignedBy);
        $this->flush();

        return $settlement;
    }

    public function settle(string $id, string $method, ?string $reference, string $settledBy): CommissionSettlement
    {
        $settlement = $this->getOrFail($id);

        if (!in_array($method, ['efectivo', 'transferencia'], true)) {
            throw new ValidationException('settlementMethod debe ser efectivo o transferencia');
        }
        if ($method === 'transferencia' && !$reference) {
            throw new ValidationException('settlementReference es requerido para transferencia');
        }

        if (!$this->settlementRepository->markStatusIfCurrent($id, 'pending_settlement', 'settled')) {
            throw new BusinessException('Esta liquidación no está lista para liquidarse');
        }

        $settlement->setStatus('settled');
        $settlement->setSettlementMethod($method);
        $settlement->setSettlementReference($reference ?: null);
        $settlement->setSettledAt(new \DateTimeImmutable());
        $settlement->setSettledBy($settledBy);
        $this->flush();

        return $settlement;
    }

    public function close(string $id, string $closedBy): CommissionSettlement
    {
        $settlement = $this->getOrFail($id);

        if (!$this->settlementRepository->markStatusIfCurrent($id, 'settled', 'closed')) {
            throw new BusinessException('Esta liquidación no está lista para cerrarse');
        }

        $settlement->setStatus('closed');
        $settlement->setClosedAt(new \DateTimeImmutable());
        $settlement->setClosedBy($closedBy);
        $this->flush();

        return $settlement;
    }

    public function get(string $id): ?CommissionSettlement
    {
        return $this->settlementRepository->find($id);
    }

    /**
     * @return CommissionSettlement[]
     */
    public function list(array $filters): array
    {
        return $this->settlementRepository->findAllFiltered($filters);
    }

    /**
     * Genera un PIN de un solo uso y lo envía por WhatsApp al teléfono del usuario interno
     * ($username) que va a realizar la siguiente acción sensible sobre esta liquidación — exigido
     * únicamente al llamar desde /api/v1 (sin sesión de panel), igual que
     * BusinessReconciliationService::requestApprovalPin() para el negocio externo.
     */
    public function requestPin(string $id, string $username): void
    {
        $settlement = $this->getOrFail($id);
        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            throw new NotFoundException('Usuario no encontrado');
        }
        if (!$user->getPhone()) {
            throw new BusinessException('El usuario no tiene teléfono configurado');
        }

        $lastRequested = $settlement->getApprovalPinRequestedAt();
        if ($lastRequested !== null && $lastRequested > new \DateTimeImmutable('-' . self::PIN_MIN_RESEND_SECONDS . ' seconds')) {
            throw new BusinessException('Espera un minuto antes de solicitar otro código');
        }

        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $settlement->setApprovalPinHash($this->passwordHasher->hashPassword($settlement, $pin));
        $settlement->setApprovalPinExpiresAt(new \DateTimeImmutable('+' . self::PIN_TTL_MINUTES . ' minutes'));
        $settlement->setApprovalPinAttempts(0);
        $settlement->setApprovalPinRequestedAt(new \DateTimeImmutable());
        $settlement->setApprovalPinVerifiedAt(null);
        $settlement->setPinVerifiedFor(null);
        $this->flush();

        try {
            $message = $this->twig->render('emails/whatsapp/commission_settlement_pin.txt.twig', [
                'userName' => $user->getName(),
                'settlementNumber' => $settlement->getSettlementNumber(),
                'pinCode' => $pin,
                'ttlMinutes' => self::PIN_TTL_MINUTES,
            ]);
            $this->openWa->sendMessage($user->getPhone(), $message);
        } catch (\Throwable $e) {
            throw new BusinessException('No se pudo enviar el código de verificación, intenta de nuevo');
        }
    }

    /**
     * Verifica el PIN enviado por requestPin(). Un solo uso: si es correcto, invalida el hash y
     * marca approvalPinVerifiedAt junto con $username — assertPinVerified() exige que la misma
     * persona que verificó sea quien realiza la acción.
     */
    public function verifyPin(string $id, string $username, string $pin): void
    {
        $settlement = $this->getOrFail($id);

        if ($settlement->getApprovalPinHash() === null) {
            throw new ValidationException('Debes solicitar un código de verificación antes de continuar');
        }
        if ($settlement->getApprovalPinExpiresAt() < new \DateTimeImmutable()) {
            throw new ValidationException('El código de verificación expiró, solicita uno nuevo');
        }
        if ($settlement->getApprovalPinAttempts() >= self::PIN_MAX_ATTEMPTS) {
            throw new ValidationException('Superaste el número de intentos, solicita un código nuevo');
        }

        if (!$this->passwordHasher->isPasswordValid($settlement, $pin)) {
            $settlement->setApprovalPinAttempts($settlement->getApprovalPinAttempts() + 1);
            $this->flush();

            $remaining = self::PIN_MAX_ATTEMPTS - $settlement->getApprovalPinAttempts();
            throw new ValidationException("Código incorrecto, te quedan {$remaining} intento(s)");
        }

        $settlement->setApprovalPinHash(null);
        $settlement->setApprovalPinExpiresAt(null);
        $settlement->setApprovalPinVerifiedAt(new \DateTimeImmutable());
        $settlement->setPinVerifiedFor($username);
        $this->flush();
    }

    /**
     * Exigido antes de approve/assignPayoutAccount/settle/close vía /api/v1 — el PIN debe estar
     * verificado, fresco (dentro de PIN_VERIFIED_TTL_MINUTES) y verificado por ESTE MISMO usuario,
     * no por otro que haya verificado el suyo en la misma ventana.
     */
    public function assertPinVerified(CommissionSettlement $settlement, string $username): void
    {
        $verifiedAt = $settlement->getApprovalPinVerifiedAt();
        if ($verifiedAt === null || $verifiedAt < new \DateTimeImmutable('-' . self::PIN_VERIFIED_TTL_MINUTES . ' minutes')) {
            throw new ValidationException('Debes verificar tu código antes de continuar');
        }
        if ($settlement->getPinVerifiedFor() !== $username) {
            throw new ValidationException('El código fue verificado por otro usuario');
        }
    }

    /**
     * Si el Rol de $username no tiene el permiso pedido, corta acá — defensa en profundidad además
     * del scope OAuth2 del cliente que llama: el scope certifica que el sistema externo puede
     * invocar este tipo de endpoint, esto certifica que ESA PERSONA puntual puede hacer esta acción
     * dentro del panel (igual que denyAccessUnlessGranted() en el controlador Admin).
     */
    public function assertUserHasPermission(string $username, string $action): void
    {
        $user = $this->userRepository->findByUsername($username);
        if (!$user || !$user->getRole()) {
            throw new BusinessException('El usuario no tiene un rol asignado');
        }

        $allowed = $user->getRole()->getPermissions()['commission_settlements'] ?? [];
        if (!in_array($action, $allowed, true)) {
            throw new BusinessException("El usuario no tiene el permiso commission_settlements:{$action}");
        }
    }

    private function getOrFail(string $id): CommissionSettlement
    {
        $settlement = $this->get($id);
        if (!$settlement) {
            throw new NotFoundException('Commission settlement not found');
        }

        return $settlement;
    }
}
