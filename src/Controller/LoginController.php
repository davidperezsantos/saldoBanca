<?php

namespace App\Controller;

use App\Services\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard_page');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['POST'])]
    public function request(Request $request, PasswordResetService $passwordResetService): Response
    {
        $username = $request->request->get('_username');

        try {
            $passwordResetService->requestReset($username);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al enviar: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Si el usuario existe, se envió un enlace de restablecimiento.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password_confirm')]
    public function confirm(string $token, Request $request, PasswordResetService $passwordResetService): Response
    {
        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            if (!$password) {
                $this->addFlash('error', 'Token inválido o contraseña vacía.');
                return $this->redirectToRoute('app_login');
            }

            try {
                $passwordResetService->confirmReset($token, $password);
            } catch (\Exception) {
                $this->addFlash('error', 'Token inválido o contraseña vacía.');
                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('success', 'Contraseña restablecida exitosamente. Inicia sesión con tu nueva contraseña.');
            return $this->redirectToRoute('app_login');
        }

        $user = $passwordResetService->findUserByToken($token);
        if (!$user) {
            $this->addFlash('error', 'Token de restablecimiento inválido o expirado.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('login/reset_password.html.twig', [
            'token' => $token,
            'name' => $user->getName(),
        ]);
    }
}
