<?php

namespace App\Services\Balance;

use App\DTO\Balance\AccountDto;
use App\Entity\Balance\Account;
use App\Entity\Balance\AccountBalance;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\AccountRepository;
use App\Repository\Balance\AccountBalanceRepository;
use App\Services\BaseService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\Notifications\OpenWaService;
use App\Services\SystemCurrencyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class AccountService extends BaseService
{
    private AccountRepository $accountRepository;
    private AccountBalanceRepository $balanceRepository;
    private SystemCurrencyService $systemCurrencyService;
    private APIExchangeRate $apiExchangeRate;

    public function __construct(
        EntityManagerInterface $entityManager,
        AccountRepository $accountRepository,
        AccountBalanceRepository $balanceRepository,
        SystemCurrencyService $systemCurrencyService,
        APIExchangeRate $apiExchangeRate,
        private UserPasswordHasherInterface $passwordHasher,
        private OpenWaService $openWa,
        private Environment $twig,
    ) {
        parent::__construct($entityManager);
        $this->accountRepository = $accountRepository;
        $this->balanceRepository = $balanceRepository;
        $this->systemCurrencyService = $systemCurrencyService;
        $this->apiExchangeRate = $apiExchangeRate;
    }

    public function createAccount(AccountDto $dto): Account
    {
        $this->validatePayoutPercents($dto->payoutCurrencyPercent, $dto->payoutSecondaryCurrencyPercent);
        $isBusiness = $dto->accountType === 'business';

        $account = new Account();
        $account->setAccountNumber($this->generateAccountNumber());
        $account->setAccountType($dto->accountType);
        $account->setBusinessName($dto->businessName);
        $account->setDocumentType($dto->documentType);
        $account->setDocumentNumber($dto->documentNumber);
        $account->setEmail($dto->email);
        $account->setPhone($dto->phone);
        $account->setDefaultCurrency($dto->defaultCurrency);
        $account->setCreditLimit($dto->creditLimit);
        $account->setAllowTransfers($dto->allowTransfers);
        $account->setAllowAuthorizedUsers($dto->allowAuthorizedUsers);
        $account->setMaxPerTransfer($dto->maxPerTransfer);
        $account->setMaxDaily($dto->maxDaily);
        $account->setMaxMonthly($dto->maxMonthly);
        $account->setPayoutCurrencyPercent($isBusiness ? $dto->payoutCurrencyPercent : null);
        $account->setPayoutSecondaryCurrencyPercent($isBusiness ? $dto->payoutSecondaryCurrencyPercent : null);

        $this->persist($account);

        $balance = new AccountBalance();
        $balance->setAccount($account);
        $balance->setCurrency($dto->defaultCurrency);
        $balance->setAvailableBalance('0.00');
        $balance->setPendingBalance('0.00');
        $balance->setReservedBalance('0.00');
        $balance->setTotalRecharged('0.00');
        $balance->setTotalTransferred('0.00');
        $balance->setTotalInvoiced('0.00');

        $this->persist($balance);
        $this->flush();

        $this->rotatePin($account);

        return $account;
    }

    /**
     * Valida el PIN vigente del titular (cliente o negocio) — usado por
     * InvoiceService::processPayment() para exigir el código antes de pagar una factura propia.
     */
    public function verifyPin(Account $account, string $pinCode): bool
    {
        return $account->getPinCode() !== null && $this->passwordHasher->isPasswordValid($account, $pinCode);
    }

    /**
     * Solicitud explícita (iniciada por el negocio vía API) de un código nuevo para el cliente —
     * a diferencia de rotatePin(), que es interna (creación de cuenta / post-pago), acá se exige
     * teléfono configurado (si no, falla claro en vez de mandar nada silenciosamente) y hay un
     * cooldown de 60s para no spamear WhatsApp con reenvíos.
     */
    public function requestPin(Account $account): void
    {
        if (!$account->getPhone()) {
            throw new BusinessException('La cuenta no tiene teléfono configurado');
        }
        if ($account->getPinRequestedAt() !== null
            && $account->getPinRequestedAt() > new \DateTimeImmutable('-60 seconds')) {
            throw new BusinessException('Espera un minuto antes de solicitar otro código');
        }

        $account->setPinRequestedAt(new \DateTimeImmutable());
        $this->rotatePin($account);
    }

    /**
     * Genera un PIN nuevo, lo guarda hasheado y lo manda por WhatsApp — mismo mecanismo que
     * AuthorizedService::generateNewPin()/notifyNewPin(), ahora para la cuenta misma. Se llama al
     * crear la cuenta (primer PIN) y después de cada pago exitoso (rotación, un solo uso).
     */
    public function rotatePin(Account $account): void
    {
        $newPin = $this->generateNewPin();
        $account->setPinCode($this->passwordHasher->hashPassword($account, $newPin));
        $this->flush();
        $this->notifyNewPin($account, $newPin);
    }

    private function generateNewPin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function notifyNewPin(Account $account, string $plainPin): void
    {
        if (!$account->getPhone()) {
            return;
        }

        try {
            $message = $this->twig->render('emails/whatsapp/new_pin.txt.twig', [
                'name' => $account->getBusinessName(),
                'pinCode' => $plainPin,
            ]);
            $this->openWa->sendMessage($account->getPhone(), $message);
        } catch (\Exception $e) {
            // Silently fail - notification is best-effort, mismo criterio que
            // AuthorizedService::notifyNewPin().
        }
    }

    public function updateAccount(string $id, AccountDto $dto): Account
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        $this->validatePayoutPercents($dto->payoutCurrencyPercent, $dto->payoutSecondaryCurrencyPercent);
        $isBusiness = $dto->accountType === 'business';

        $account->setAccountType($dto->accountType);
        $account->setBusinessName($dto->businessName);
        $account->setDocumentType($dto->documentType);
        $account->setDocumentNumber($dto->documentNumber);
        $account->setEmail($dto->email);
        $account->setPhone($dto->phone);
        $account->setDefaultCurrency($dto->defaultCurrency);
        $account->setCreditLimit($dto->creditLimit);
        $account->setAllowTransfers($dto->allowTransfers);
        $account->setAllowAuthorizedUsers($dto->allowAuthorizedUsers);
        $account->setMaxPerTransfer($dto->maxPerTransfer);
        $account->setMaxDaily($dto->maxDaily);
        $account->setMaxMonthly($dto->maxMonthly);
        $account->setPayoutCurrencyPercent($isBusiness ? $dto->payoutCurrencyPercent : null);
        $account->setPayoutSecondaryCurrencyPercent($isBusiness ? $dto->payoutSecondaryCurrencyPercent : null);

        $this->flush();

        return $account;
    }

    private function validatePayoutPercents(?string $currencyPercent, ?string $secondaryPercent): void
    {
        if ($currencyPercent === null && $secondaryPercent === null) {
            return;
        }

        if ($currencyPercent === null || $secondaryPercent === null) {
            throw new ValidationException(
                'payoutCurrencyPercent y payoutSecondaryCurrencyPercent deben configurarse juntos o dejarse ambos vacíos'
            );
        }

        if (bccomp(bcadd($currencyPercent, $secondaryPercent, 2), '100.00', 2) !== 0) {
            throw new ValidationException('payoutCurrencyPercent + payoutSecondaryCurrencyPercent deben sumar 100');
        }
    }

    public function getAccount(string $id): ?Account
    {
        return $this->accountRepository->find($id);
    }

    public function getAccountByNumber(string $number): ?Account
    {
        return $this->accountRepository->findByAccountNumber($number);
    }

    public function getAccountByDocument(string $documentType, string $documentNumber): ?Account
    {
        return $this->accountRepository->findByDocument($documentType, $documentNumber);
    }

    public function listAccounts(array $filters = []): array
    {
        $qb = $this->accountRepository->createQueryBuilder('a');

        if (isset($filters['status'])) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['accountType'])) {
            $qb->andWhere('a.accountType = :accountType')
               ->setParameter('accountType', $filters['accountType']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere('a.businessName LIKE :search OR a.documentNumber LIKE :search OR a.accountNumber LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->orderBy('a.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (isset($filters['offset'])) {
            $qb->setFirstResult($filters['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    public function changeStatus(string $id, string $status): Account
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        $account->setStatus($status);
        $this->flush();

        return $account;
    }

    public function getAccountSummary(string $id): array
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        // El saldo real vive en la moneda base del sistema (todo lo que entra se convierte a esa
        // moneda), no necesariamente en account.defaultCurrency — se busca por la base y se
        // convierte a defaultCurrency solo para mostrar, igual que TransferService::
        // getTransferLimits()/Admin\AccountController::index(), porque es la moneda en la que el
        // usuario configuró la cuenta y espera ver sus montos.
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $balance = $this->balanceRepository->findByAccountAndCurrency($id, $baseCurrency);
        $displayCurrency = $account->getDefaultCurrency();

        $balanceData = null;
        if ($balance) {
            $rate = $displayCurrency !== $baseCurrency ? $this->apiExchangeRate->getRate($displayCurrency) : null;
            $convert = fn(string $amount) => $rate !== null
                ? (string) round((float) bcmul($amount, (string) $rate, 4), 2)
                : $amount;

            $balanceData = [
                'available' => $convert($balance->getAvailableBalance()),
                'pending' => $convert($balance->getPendingBalance()),
                'reserved' => $convert($balance->getReservedBalance()),
                'currency' => $displayCurrency,
            ];
        }

        return [
            'account' => [
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ],
            'balance' => $balanceData,
        ];
    }

    private function generateAccountNumber(): string
    {
        $prefix = 'SAL';
        $timestamp = date('ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return $prefix . $timestamp . $random;
    }
}
