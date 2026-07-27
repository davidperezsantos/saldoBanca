<?php

namespace App\Services;

use App\Exception\BusinessException;
use App\Repository\UserRepository;
use App\Services\Balance\AuthorizedService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Flujo de "olvidé mi contraseña" self-service: cualquier usuario (cliente, negocio o autorizado)
 * lo dispara con su username/email, sin estar logueado. Compartido entre el panel web
 * (LoginController) y la API (Controller\Api\AuthController), para no duplicar la lógica.
 */
class PasswordResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private AuthorizedService $authorizedService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggableSystemActor $systemActor,
    ) {
    }

    /**
     * Si el usuario existe, genera un token y le envía el enlace por WhatsApp. Si no existe, no
     * hace nada — el llamador siempre debe responder el mismo mensaje genérico ("si existe, se
     * envió"), para no revelar si un username/email está registrado.
     */
    public function requestReset(string $usernameOrEmail): void
    {
        $user = $this->userRepository->findByUsername($usernameOrEmail)
            ?? $this->userRepository->findByEmail($usernameOrEmail);

        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $this->systemActor->actAsSystem('restablecer-password');
        $this->entityManager->flush();

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password_confirm',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->authorizedService->sendResetLink($user, $resetUrl);
    }

    public function confirmReset(string $token, string $newPassword): void
    {
        $user = $this->userRepository->findOneBy(['resetToken' => $token]);
        if (!$user) {
            throw new BusinessException('Invalid or expired reset token');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setResetToken(null);
        $this->systemActor->actAsSystem('restablecer-password');
        $this->entityManager->flush();
    }

    public function findUserByToken(string $token): ?\App\Entity\User
    {
        return $this->userRepository->findOneBy(['resetToken' => $token]);
    }
}
