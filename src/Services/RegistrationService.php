<?php

namespace App\Services;

use App\Entity\Balance\Account;
use App\Entity\User;
use App\Repository\Balance\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UsernameGenerator $usernameGenerator,
        private RoleSeedService $roleSeedService,
        private AccountRepository $accountRepository,
    ) {
    }

    public function registerClient(array $data): array
    {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';
        $documentType = $data['documentType'] ?? '';
        $documentNumber = $data['documentNumber'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            throw new \InvalidArgumentException('name, email and password are required');
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            throw new \RuntimeException('Email already exists');
        }

        $username = $this->usernameGenerator->generate($name);

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setName($name);
        $user->setPhone($phone);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);
        $user->setRole($this->roleSeedService->ensureRoleExists('cliente'));

        $this->entityManager->persist($user);

        $account = new Account();
        $account->setAccountNumber($this->generateAccountNumber());
        $account->setAccountType('client');
        $account->setBusinessName($name);
        $account->setDocumentType($documentType);
        $account->setDocumentNumber($documentNumber);
        $account->setStatus('active');
        $account->setUser($user);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return ['user' => $user, 'account' => $account];
    }

    public function registerBusiness(array $data): Account
    {
        $businessName = $data['businessName'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $documentType = $data['documentType'] ?? '';
        $documentNumber = $data['documentNumber'] ?? '';

        if (empty($businessName)) {
            throw new \InvalidArgumentException('businessName is required');
        }

        $account = new Account();
        $account->setAccountNumber($this->generateAccountNumber());
        $account->setAccountType('business');
        $account->setBusinessName($businessName);
        $account->setDocumentType($documentType);
        $account->setDocumentNumber($documentNumber);
        $account->setEmail($email);
        $account->setPhone($phone);
        $account->setStatus('pending');

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    public function approveBusiness(string $accountId, array $data): User
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        $name = $data['name'] ?? $account->getBusinessName();
        $email = $data['email'] ?? $account->getEmail() ?? '';
        $phone = $data['phone'] ?? $account->getPhone() ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw new \InvalidArgumentException('email and password are required');
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            throw new \RuntimeException('Email already exists');
        }

        $username = $this->usernameGenerator->generate($name);

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setName($name);
        $user->setPhone($phone);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);
        $user->setRole($this->roleSeedService->ensureRoleExists('emprendedor'));

        $this->entityManager->persist($user);

        $account->setUser($user);
        $account->setStatus('active');
        if ($email) {
            $account->setEmail($email);
        }
        if ($phone) {
            $account->setPhone($phone);
        }

        $this->entityManager->flush();

        return $user;
    }

    private function generateAccountNumber(): string
    {
        $prefix = 'SAL';
        $timestamp = date('ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return $prefix . $timestamp . $random;
    }
}
