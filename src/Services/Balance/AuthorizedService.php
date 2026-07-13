<?php

namespace App\Services\Balance;

use App\DTO\Balance\AuthorizedDto;
use App\Entity\Balance\AuthorizedUser;
use App\Repository\Balance\AuthorizedUserRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

class AuthorizedService extends BaseService
{
    private AuthorizedUserRepository $authorizedRepository;
    private AccountRepository $accountRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        AuthorizedUserRepository $authorizedRepository,
        AccountRepository $accountRepository
    ) {
        parent::__construct($entityManager);
        $this->authorizedRepository = $authorizedRepository;
        $this->accountRepository = $accountRepository;
    }

    public function createAuthorized(AuthorizedDto $dto): AuthorizedUser
    {
        $account = $this->accountRepository->find($dto->accountId);
        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        if (!$account->isAllowAuthorizedUsers()) {
            throw new \RuntimeException('Authorized users are not allowed for this account');
        }

        $existing = $this->authorizedRepository->findByDocumentNumber($dto->documentNumber);
        if ($existing) {
            throw new \RuntimeException('Document number already registered');
        }

        $authorized = new AuthorizedUser();
        $authorized->setAccount($account);
        $authorized->setUserName($dto->userName);
        $authorized->setUserEmail($dto->userEmail);
        $authorized->setUserPhone($dto->userPhone);
        $authorized->setDocumentType($dto->documentType);
        $authorized->setDocumentNumber($dto->documentNumber);
        $authorized->setMaxAmount($dto->maxAmount);
        $authorized->setDailyLimit($dto->dailyLimit);
        $authorized->setMonthlyLimit($dto->monthlyLimit);
        $authorized->setPinCode($dto->pinCode);
        $authorized->setStatus('active');

        $this->persist($authorized);
        $this->flush();

        return $authorized;
    }

    public function updateAuthorized(string $id, AuthorizedDto $dto): AuthorizedUser
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new \RuntimeException('Authorized user not found');
        }

        $authorized->setUserName($dto->userName);
        $authorized->setUserEmail($dto->userEmail);
        $authorized->setUserPhone($dto->userPhone);
        $authorized->setDocumentType($dto->documentType);
        $authorized->setDocumentNumber($dto->documentNumber);
        $authorized->setMaxAmount($dto->maxAmount);
        $authorized->setDailyLimit($dto->dailyLimit);
        $authorized->setMonthlyLimit($dto->monthlyLimit);
        $authorized->setPinCode($dto->pinCode);

        $this->flush();

        return $authorized;
    }

    public function deleteAuthorized(string $id): void
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new \RuntimeException('Authorized user not found');
        }

        $this->remove($authorized);
        $this->flush();
    }

    public function changeStatus(string $id, string $status): AuthorizedUser
    {
        $authorized = $this->authorizedRepository->find($id);
        if (!$authorized) {
            throw new \RuntimeException('Authorized user not found');
        }

        $authorized->setStatus($status);
        $this->flush();

        return $authorized;
    }

    public function verifyAuthorized(string $documentNumber): ?AuthorizedUser
    {
        return $this->authorizedRepository->findByDocumentNumber($documentNumber);
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
