<?php

namespace App\Services\Balance;

use App\Entity\Balance\BusinessPayoutAccount;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\AccountRepository;
use App\Repository\Balance\BusinessPayoutAccountRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cuentas reales (bancarias/de pago) que un negocio registra para indicar a dónde debe
 * transferírsele el dinero al liquidar una conciliación (ver
 * BusinessReconciliationService::approveByBusiness/getPayoutAccountsForApproval).
 */
class BusinessPayoutAccountService extends BaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private BusinessPayoutAccountRepository $payoutAccountRepository,
        private AccountRepository $accountRepository,
    ) {
        parent::__construct($entityManager);
    }

    /**
     * @return BusinessPayoutAccount[]
     */
    public function listByAccount(string $accountId): array
    {
        $account = $this->getBusinessAccount($accountId);

        return $this->payoutAccountRepository->findByAccount($account);
    }

    public function create(string $accountId, array $data): BusinessPayoutAccount
    {
        $account = $this->getBusinessAccount($accountId);

        $payoutAccount = new BusinessPayoutAccount();
        $payoutAccount->setAccount($account);
        $this->applyData($payoutAccount, $data);

        $this->persist($payoutAccount);
        $this->flush();

        return $payoutAccount;
    }

    public function update(string $accountId, string $id, array $data): BusinessPayoutAccount
    {
        $payoutAccount = $this->getForAccount($accountId, $id);
        $this->applyData($payoutAccount, $data);
        $this->flush();

        return $payoutAccount;
    }

    public function delete(string $accountId, string $id): void
    {
        $payoutAccount = $this->getForAccount($accountId, $id);
        $this->remove($payoutAccount);
        $this->flush();
    }

    private function applyData(BusinessPayoutAccount $payoutAccount, array $data): void
    {
        $alias = trim((string) ($data['alias'] ?? ''));
        $currency = trim((string) ($data['currency'] ?? ''));
        $accountNumber = trim((string) ($data['accountNumber'] ?? ''));

        if ($alias === '') {
            throw new ValidationException('El alias es requerido');
        }
        if ($currency === '' || strlen($currency) !== 3) {
            throw new ValidationException('La moneda debe ser un código de 3 letras');
        }
        if ($accountNumber === '') {
            throw new ValidationException('El número de cuenta es requerido');
        }

        $payoutAccount->setAlias($alias);
        $payoutAccount->setCurrency(strtoupper($currency));
        $payoutAccount->setAccountNumber($accountNumber);
        $payoutAccount->setBankName(($data['bankName'] ?? null) ?: null);
        $payoutAccount->setSwift(($data['swift'] ?? null) ?: null);
        $payoutAccount->setAccountHolder(($data['accountHolder'] ?? null) ?: null);

        if (array_key_exists('isActive', $data)) {
            $payoutAccount->setIsActive((bool) $data['isActive']);
        }
    }

    private function getBusinessAccount(string $accountId)
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundException('Account not found');
        }
        if ($account->getAccountType() !== 'business') {
            throw new BusinessException('Solo las cuentas de tipo negocio pueden tener cuentas de pago');
        }

        return $account;
    }

    private function getForAccount(string $accountId, string $id): BusinessPayoutAccount
    {
        $account = $this->getBusinessAccount($accountId);

        $payoutAccount = $this->payoutAccountRepository->find($id);
        if (!$payoutAccount || $payoutAccount->getAccount()?->getId()?->toString() !== $account->getId()->toString()) {
            throw new NotFoundException('Payout account not found');
        }

        return $payoutAccount;
    }
}
