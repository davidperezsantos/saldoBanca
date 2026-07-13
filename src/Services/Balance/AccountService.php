<?php

namespace App\Services\Balance;

use App\DTO\Balance\AccountDto;
use App\Entity\Balance\Account;
use App\Entity\Balance\AccountBalance;
use App\Repository\Balance\AccountRepository;
use App\Repository\Balance\AccountBalanceRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

class AccountService extends BaseService
{
    private AccountRepository $accountRepository;
    private AccountBalanceRepository $balanceRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        AccountRepository $accountRepository,
        AccountBalanceRepository $balanceRepository
    ) {
        parent::__construct($entityManager);
        $this->accountRepository = $accountRepository;
        $this->balanceRepository = $balanceRepository;
    }

    public function createAccount(AccountDto $dto): Account
    {
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

        return $account;
    }

    public function updateAccount(string $id, AccountDto $dto): Account
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

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

        $this->flush();

        return $account;
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
            throw new \RuntimeException('Account not found');
        }

        $account->setStatus($status);
        $this->flush();

        return $account;
    }

    public function getAccountSummary(string $id): array
    {
        $account = $this->accountRepository->find($id);

        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        $balance = $this->balanceRepository->findByAccountAndCurrency(
            $id,
            $account->getDefaultCurrency()
        );

        return [
            'account' => [
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ],
            'balance' => $balance ? [
                'available' => $balance->getAvailableBalance(),
                'pending' => $balance->getPendingBalance(),
                'reserved' => $balance->getReservedBalance(),
                'currency' => $balance->getCurrency(),
            ] : null,
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
