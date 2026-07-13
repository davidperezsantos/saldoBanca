<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('User email', 'admin@saldobanca.com');
        $password = $io->ask('User password', 'password');
        $name = $io->ask('User name', 'Admin User');

        $roleRepo = $this->entityManager->getRepository(Role::class);
        $roles = $roleRepo->findAll();
        $roleNames = array_map(fn($r) => $r->getName(), $roles);

        $selectedRole = $io->choice('Role', $roleNames, 'super_admin');

        $userRepository = $this->entityManager->getRepository(User::class);
        $existingUser = $userRepository->findByEmail($email);

        if ($existingUser) {
            $io->error('User with this email already exists');
            return Command::FAILURE;
        }

        $role = $roleRepo->findByName($selectedRole);

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRole($role);
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('User created successfully');
        $io->note('Email: ' . $email);
        $io->note('Role: ' . $selectedRole);

        return Command::SUCCESS;
    }
}
