<?php

namespace App\Services\Balance;

use App\DTO\Balance\AuthorizedDto;
use App\Entity\Balance\Account;
use App\Entity\Balance\AuthorizedUser;
use App\Entity\User;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\AuthorizedUserRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use App\Services\Notifications\OpenWaService;
use App\Services\RoleSeedService;
use App\Services\SystemCurrencyService;
use App\Services\UsernameGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class AuthorizedService extends BaseService
{
    private AuthorizedUserRepository $authorizedRepository;
    private AccountRepository $accountRepository;
    private BalanceService $balanceService;
    private InvoiceService $invoiceService;
    private UserPasswordHasherInterface $passwordHasher;
    private UsernameGenerator $usernameGenerator;
    private RoleSeedService $roleSeedService;
    private SystemCurrencyService $systemCurrencyService;
    private OpenWaService $openWa;
    private Environment $twig;
    private OperationEventService $operationEventService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AuthorizedUserRepository $authorizedRepository,
        AccountRepository $accountRepository,
        BalanceService $balanceService,
        InvoiceService $invoiceService,
        UserPasswordHasherInterface $passwordHasher,
        UsernameGenerator $usernameGenerator,
        RoleSeedService $roleSeedService,
        SystemCurrencyService $systemCurrencyService,
        OpenWaService $openWa,
        Environment $twig,
        OperationEventService $operationEventService,
    ) {
        parent::__construct($entityManager);
        $this->authorizedRepository = $authorizedRepository;
        $this->accountRepository = $accountRepository;
        $this->balanceService = $balanceService;
        $this->invoiceService = $invoiceService;
        $this->passwordHasher = $passwordHasher;
        $this->usernameGenerator = $usernameGenerator;
        $this->roleSeedService = $roleSeedService;
        $this->systemCurrencyService = $systemCurrencyService;
        $this->openWa = $openWa;
        $this->twig = $twig;
        $this->operationEventService = $operationEventService;
    }

    public function createAuthorized(AuthorizedDto $dto, ?string $generatedPassword = null): AuthorizedUser
    {
        if (!$dto->accountId) {
            throw new ValidationException('Account ID is required');
        }

        $account = $this->accountRepository->find($dto->accountId);
        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        if (!$account->isAllowAuthorizedUsers()) {
            throw new BusinessException('Authorized users are not allowed for this account');
        }

        $existing = $this->authorizedRepository->findByDocumentNumber($dto->documentNumber);
        if ($existing) {
            throw new BusinessException('Document number already registered');
        }

        if ($dto->maxAmount !== null) {
            $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
            $accBalance = $this->balanceService->getBalance($account->getId()->toString(), $baseCurrency);
            $available = $accBalance ? $accBalance->getAvailableBalance() : '0.00';

            if (bccomp($dto->maxAmount, $available, 2) > 0) {
                throw new BusinessException("El monto asignado ({$dto->maxAmount}) supera el saldo disponible ({$available})");
            }
        }

        $password = $generatedPassword ?? bin2hex(random_bytes(8));
        $username = $this->usernameGenerator->generate($dto->userName);

        $user = new User();
        $user->setEmail($dto->userEmail);
        $user->setUsername($username);
        $user->setName($dto->userName);
        $user->setPhone($dto->userPhone ?? '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);
        $user->setAssignedRoles([$this->roleSeedService->ensureRoleExists('cliente')]);

        $this->persist($user);

        $authorized = new AuthorizedUser();
        $authorized->setAccount($account);
        $authorized->setUser($user);
        $authorized->setUserName($dto->userName);
        $authorized->setUserEmail($dto->userEmail);
        $authorized->setUserPhone($dto->userPhone);
        $authorized->setDocumentType($dto->documentType);
        $authorized->setDocumentNumber($dto->documentNumber);
        $authorized->setMaxAmount($dto->maxAmount);
        $authorized->setDailyLimit($dto->dailyLimit);
        $authorized->setMonthlyLimit($dto->monthlyLimit);
        $plainPin = $dto->pinCode ?? $this->generateNewPin();
        $authorized->setPinCode($this->passwordHasher->hashPassword($authorized, $plainPin));
        $authorized->setStatus('active');

        $hasReservation = $dto->maxAmount !== null && bccomp($dto->maxAmount, '0', 2) > 0;
        if ($hasReservation) {
            $authorized->setReservedAmount($dto->maxAmount);
        }

        $this->persist($authorized);
        $this->flush();

        if ($hasReservation) {
            $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
            $this->balanceService->reserveBalance(
                accountId: $account->getId()->toString(),
                amount: $dto->maxAmount,
                currency: $baseCurrency,
                referenceType: 'authorized',
                referenceId: $authorized->getId()->toString(),
                description: 'Reserva de cupo para autorizado ' . $dto->userName,
            );
        }

        $this->sendCredentialsWhatsApp($authorized, $username, $password);

        return $authorized;
    }

    /**
     * Crea (si no existe ya) el registro de AuthorizedUser "de sí mismo" para el dueño de la cuenta,
     * vinculado a su propio User existente (sin crear un segundo login). Reutiliza el mismo
     * mecanismo de PIN/límites de los autorizados delegados en vez de duplicar el concepto en
     * Account. Sin maxAmount: su gasto sale directo de availableBalance (es su propio dinero, no
     * hay nada que reservar), a diferencia de un autorizado delegado.
     */
    public function ensureSelfAuthorized(Account $account): AuthorizedUser
    {
        $user = $account->getUser();
        if (!$user) {
            throw new BusinessException('Account has no linked user yet');
        }

        $existing = $this->authorizedRepository->findOneBy(['user' => $user]);
        if ($existing) {
            return $existing;
        }

        $authorized = new AuthorizedUser();
        $authorized->setAccount($account);
        $authorized->setUser($user);
        $authorized->setUserName($user->getName() ?? $account->getBusinessName());
        $authorized->setUserEmail($user->getEmail() ?? $account->getEmail() ?? '');
        $authorized->setUserPhone($user->getPhone() ?: $account->getPhone());
        $authorized->setDocumentType($account->getDocumentType());
        $authorized->setDocumentNumber($account->getDocumentNumber());
        $authorized->setPinCode($this->passwordHasher->hashPassword($authorized, $this->generateNewPin()));
        $authorized->setStatus('active');

        $this->persist($authorized);
        $this->flush();

        return $authorized;
    }

    private function generateNewPin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function notifyNewPin(AuthorizedUser $authorized, string $plainPin): void
    {
        $phone = $authorized->getUserPhone();
        if (!$phone) {
            return;
        }

        try {
            $message = $this->twig->render('emails/whatsapp/new_pin.txt.twig', [
                'name' => $authorized->getUserName(),
                'pinCode' => $plainPin,
            ]);
            $this->openWa->sendMessage($phone, $message);
        } catch (\Exception $e) {
            // Silently fail - notification is best-effort
        }
    }

    /**
     * Solicitud explícita (iniciada por el negocio vía API) de un código nuevo para el autorizado
     * — a diferencia de la rotación que ya hace spend() tras un cargo exitoso, acá se exige
     * teléfono configurado (si no, falla claro en vez de mandar nada silenciosamente) y hay un
     * cooldown de 60s para no spamear WhatsApp con reenvíos.
     */
    public function requestPin(string $id): void
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new NotFoundException('Authorized user not found');
        }
        if ($authorized->getStatus() !== 'active') {
            throw new BusinessException('Authorized user is not active');
        }
        if (!$authorized->getUserPhone()) {
            throw new BusinessException('El autorizado no tiene teléfono configurado');
        }
        if ($authorized->getPinRequestedAt() !== null
            && $authorized->getPinRequestedAt() > new \DateTimeImmutable('-60 seconds')) {
            throw new BusinessException('Espera un minuto antes de solicitar otro código');
        }

        $newPin = $this->generateNewPin();
        $authorized->setPinCode($this->passwordHasher->hashPassword($authorized, $newPin));
        $authorized->setPinRequestedAt(new \DateTimeImmutable());
        $this->flush();

        $this->notifyNewPin($authorized, $newPin);
    }

    /**
     * Consume el saldo de un autorizado (o del propio titular, vía su registro "de sí mismo") para
     * pagar una compra, validando el PIN vigente. Si trae $invoiceNumber, paga esa factura pendiente
     * (creada antes por el negocio vía /api/v1/invoices/payment) y la deja marcada como pagada; si
     * no, solo registra el movimiento con $notes como descripción libre.
     *
     * El PIN es de un solo uso: al concluir el cargo con éxito se genera uno nuevo aleatorio y se
     * notifica por WhatsApp — el que se acaba de usar deja de servir.
     */
    public function spend(string $id, string $pinCode, ?string $invoiceNumber = null, ?string $amount = null, ?string $notes = null, ?string $paymentMethod = null): array
    {
        $paymentMethod ??= 'saldo';
        if (!in_array($paymentMethod, ['saldo', 'efectivo'], true)) {
            throw new ValidationException('paymentMethod debe ser saldo o efectivo');
        }

        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new NotFoundException('Authorized user not found');
        }

        if ($authorized->getStatus() !== 'active') {
            throw new BusinessException('Authorized user is not active');
        }

        if ($authorized->getPinCode() === null || !$this->passwordHasher->isPasswordValid($authorized, $pinCode)) {
            throw new BusinessException('Invalid PIN');
        }

        $account = $authorized->getAccount();
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        $invoice = null;
        if ($invoiceNumber !== null) {
            $invoice = $this->invoiceService->findByAccountAndNumber($account->getId()->toString(), $invoiceNumber);
            if (!$invoice) {
                throw new NotFoundException("Invoice {$invoiceNumber} not found for this account");
            }
            if ($invoice->getStatus() !== 'pending') {
                throw new BusinessException("Invoice {$invoiceNumber} is not in pending status");
            }
            $amount = $invoice->getTotalAmount();
        }

        if ($amount === null || bccomp($amount, '0', 2) <= 0) {
            throw new ValidationException('A positive amount (or a valid invoiceNumber) is required');
        }

        if (!$this->checkLimits($id, $amount)) {
            throw new BusinessException('Amount exceeds authorized limits');
        }

        $description = $invoice ? "Pago de la factura {$invoice->getInvoiceNumber()}" : ($notes ?? 'Consumo de autorizado');
        if ($paymentMethod === 'efectivo') {
            $description .= ' (efectivo)';
        }

        $newPin = $this->generateNewPin();

        $result = $this->entityManager->wrapInTransaction(function () use ($authorized, $account, $amount, $baseCurrency, $invoice, $description, $id, $newPin, $paymentMethod) {
            if ($paymentMethod === 'saldo') {
                if ($authorized->getReservedAmount() !== null) {
                    $this->balanceService->deductReservedBalance(
                        accountId: $account->getId()->toString(),
                        amount: $amount,
                        currency: $baseCurrency,
                        referenceType: 'authorized',
                        referenceId: $id,
                        description: $description,
                        performedBy: $authorized->getUserName(),
                    );
                    $authorized->setReservedAmount(bcsub($authorized->getReservedAmount(), $amount, 2));
                } else {
                    $this->balanceService->deductBalance(
                        accountId: $account->getId()->toString(),
                        amount: $amount,
                        currency: $baseCurrency,
                        type: 'authorized_spend',
                        referenceType: 'authorized',
                        referenceId: $id,
                        description: $description,
                        performedBy: $authorized->getUserName(),
                    );
                }
            } elseif ($authorized->getReservedAmount() !== null) {
                // Efectivo: no se debita saldo real (el dinero no pasó por la plataforma), pero el
                // cupo local del autorizado se consume igual — los límites aplican sin importar el
                // canal de pago.
                $authorized->setReservedAmount(bcsub($authorized->getReservedAmount(), $amount, 2));
            }

            $authorized->setUsedToday(bcadd($authorized->getUsedToday(), $amount, 2));
            $authorized->setUsedThisMonth(bcadd($authorized->getUsedThisMonth(), $amount, 2));
            $authorized->setLastUsedAt(new \DateTimeImmutable());
            $authorized->setPinCode($this->passwordHasher->hashPassword($authorized, $newPin));

            $paidInvoice = null;
            if ($invoice) {
                $paidInvoice = $this->invoiceService->markPaidExternally($invoice, $authorized->getUserName(), $paymentMethod);
            }

            $this->flush();

            $this->operationEventService->log('authorized', $id, 'spend', $authorized->getUserName(), $description);

            return ['authorized' => $authorized, 'invoice' => $paidInvoice];
        });

        $this->notifyNewPin($authorized, $newPin);

        return $result;
    }

    private function sendCredentialsWhatsApp(AuthorizedUser $authorized, string $username, string $password): void
    {
        $phone = $authorized->getUserPhone();
        if (!$phone) {
            return;
        }

        try {
            $message = $this->twig->render('emails/whatsapp/welcome.txt.twig', [
                'name' => $authorized->getUserName(),
                'username' => $username,
                'password' => $password,
                'roleName' => null,
            ]);
            $this->openWa->sendMessage($phone, $message);
        } catch (\Exception $e) {
            // Silently fail - notification is best-effort
        }
    }

    public function saveResetToken(string $id, string $token): void
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new NotFoundException('Authorized user not found');
        }

        $user = $authorized->getUser();
        if (!$user) {
            throw new NotFoundException('User not found for this authorized user');
        }

        $user->setResetToken($token);
        $this->flush();
    }

    public function sendResetLink(\App\Entity\User $user, string $resetUrl): void
    {
        $phone = $user->getPhone();
        $name = $user->getName() ?? $user->getUsername();

        $authorized = $this->authorizedRepository->findOneBy(['user' => $user]);
        if ($authorized && $authorized->getUserPhone()) {
            $phone = $authorized->getUserPhone();
            $name = $authorized->getUserName();
        }

        if (!$phone) {
            throw new ValidationException('El usuario no tiene un número de teléfono registrado para enviar la notificación.');
        }

        try {
            $message = $this->twig->render('emails/whatsapp/reset_password.txt.twig', [
                'name' => $name,
                'resetUrl' => $resetUrl,
                'expirationHours' => '24',
            ]);
            $this->openWa->sendMessage($phone, $message);
        } catch (\Exception $e) {
            throw new BusinessException('Error al enviar la notificación: ' . $e->getMessage());
        }
    }

    public function resetPassword(string $id, string $resetUrl): void
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new NotFoundException('Authorized user not found');
        }

        $user = $authorized->getUser();
        if (!$user) {
            throw new NotFoundException('User not found for this authorized user');
        }

        $phone = $authorized->getUserPhone();
        if ($phone) {
            try {
                $message = $this->twig->render('emails/whatsapp/reset_password.txt.twig', [
                    'name' => $authorized->getUserName(),
                    'resetUrl' => $resetUrl,
                    'expirationHours' => '24',
                ]);
                $this->openWa->sendMessage($phone, $message);
            } catch (\Exception $e) {
                // Silently fail
            }
        }
    }

    public function updateAuthorized(string $id, AuthorizedDto $dto): AuthorizedUser
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new NotFoundException('Authorized user not found');
        }

        $authorized->setUserName($dto->userName);
        $authorized->setUserEmail($dto->userEmail);
        $authorized->setUserPhone($dto->userPhone);
        $authorized->setDocumentType($dto->documentType);
        $authorized->setDocumentNumber($dto->documentNumber);
        $authorized->setMaxAmount($dto->maxAmount);
        $authorized->setDailyLimit($dto->dailyLimit);
        $authorized->setMonthlyLimit($dto->monthlyLimit);
        if ($dto->pinCode !== null) {
            $authorized->setPinCode($this->passwordHasher->hashPassword($authorized, $dto->pinCode));
        }

        $user = $authorized->getUser();
        if ($user) {
            $user->setName($dto->userName);
            $user->setEmail($dto->userEmail);
            $user->setPhone($dto->userPhone ?? '');
        }

        $this->flush();

        return $authorized;
    }

    public function deleteAuthorized(string $id): void
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new NotFoundException('Authorized user not found');
        }

        $account = $authorized->getAccount();
        $reservedAmount = $authorized->getReservedAmount();

        $user = $authorized->getUser();
        if ($user) {
            $this->remove($user);
        }

        $this->remove($authorized);
        $this->flush();

        if ($reservedAmount !== null && bccomp($reservedAmount, '0', 2) > 0 && $account) {
            $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
            $this->balanceService->releaseBalance(
                accountId: $account->getId()->toString(),
                amount: $reservedAmount,
                currency: $baseCurrency,
                referenceType: 'authorized',
                referenceId: $id,
                description: 'Cupo liberado al eliminar autorizado',
            );
        }
    }

    public function changeStatus(string $id, string $status): AuthorizedUser
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $status) {
            $authorized = $this->authorizedRepository->find($id);
            if (!$authorized) {
                throw new NotFoundException('Authorized user not found');
            }

            $oldStatus = $authorized->getStatus();
            $reservedAmount = $authorized->getReservedAmount();
            $account = $authorized->getAccount();

            // La reserva/liberación de cupo se resuelve antes de fijar el nuevo status: si no hay
            // saldo disponible para reactivar, la transacción entera se revierte y el status no
            // queda desincronizado del saldo real. Se libera/reserva reservedAmount (lo que
            // realmente le queda tras sus gastos), no maxAmount (el tope original) — si no, cada
            // ciclo desactivar/reactivar le devolvería de más el cupo ya gastado.
            if ($reservedAmount !== null && bccomp($reservedAmount, '0', 2) > 0 && $account) {
                $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

                if ($oldStatus === 'active' && $status !== 'active') {
                    $this->balanceService->releaseBalance(
                        accountId: $account->getId()->toString(),
                        amount: $reservedAmount,
                        currency: $baseCurrency,
                        referenceType: 'authorized',
                        referenceId: $id,
                        description: 'Cupo liberado al desactivar autorizado',
                    );
                } elseif ($oldStatus !== 'active' && $status === 'active') {
                    $this->balanceService->reserveBalance(
                        accountId: $account->getId()->toString(),
                        amount: $reservedAmount,
                        currency: $baseCurrency,
                        referenceType: 'authorized',
                        referenceId: $id,
                        description: 'Cupo reservado al reactivar autorizado',
                    );
                }
            }

            $authorized->setStatus($status);
            $this->flush();

            return $authorized;
        });
    }

    public function verifyAuthorized(string $documentNumber): ?AuthorizedUser
    {
        return $this->authorizedRepository->findByDocumentNumber($documentNumber);
    }

    public function getAuthorized(string $id): ?AuthorizedUser
    {
        return $this->authorizedRepository->find($id);
    }

    public function checkLimits(string $id, string $amount): bool
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            return false;
        }

        if ($authorized->getStatus() !== 'active') {
            return false;
        }

        if ($authorized->getMaxAmount() !== null) {
            if (bccomp($amount, $authorized->getMaxAmount(), 2) > 0) {
                return false;
            }
        }

        // reservedAmount es lo que le queda de verdad de su cupo (baja con cada spend), a
        // diferencia de maxAmount que es el tope fijo por operación — sin este chequeo, un
        // autorizado podría seguir gastando en montos pequeños más allá de lo que realmente le
        // queda reservado, drenando el cupo de otros autorizados de la misma cuenta.
        if ($authorized->getReservedAmount() !== null) {
            if (bccomp($amount, $authorized->getReservedAmount(), 2) > 0) {
                return false;
            }
        }

        if ($authorized->getDailyLimit() !== null) {
            if (bccomp(bcadd($authorized->getUsedToday(), $amount, 2), $authorized->getDailyLimit(), 2) > 0) {
                return false;
            }
        }

        if ($authorized->getMonthlyLimit() !== null) {
            if (bccomp(bcadd($authorized->getUsedThisMonth(), $amount, 2), $authorized->getMonthlyLimit(), 2) > 0) {
                return false;
            }
        }

        return true;
    }

    public function resetDailyLimits(): void
    {
        $authorizedUsers = $this->authorizedRepository->findBy(['status' => 'active']);

        foreach ($authorizedUsers as $authorized) {
            $authorized->setUsedToday('0.00');
            $authorized->setDailyResetAt(new \DateTimeImmutable());
        }

        $this->flush();
    }

    public function resetMonthlyLimits(): void
    {
        $authorizedUsers = $this->authorizedRepository->findBy(['status' => 'active']);

        foreach ($authorizedUsers as $authorized) {
            $authorized->setUsedThisMonth('0.00');
            $authorized->setMonthlyResetAt(new \DateTimeImmutable());
        }

        $this->flush();
    }

    public function listAuthorized(array $filters = []): array
    {
        $qb = $this->authorizedRepository->createQueryBuilder('au');

        if (isset($filters['accountId'])) {
            $qb->andWhere('au.account = :accountId')
               ->setParameter('accountId', $filters['accountId']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('au.status = :status')
               ->setParameter('status', $filters['status']);
        }

        $qb->orderBy('au.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (isset($filters['offset'])) {
            $qb->setFirstResult($filters['offset']);
        }

        return $qb->getQuery()->getResult();
    }
}
