<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\DTO\ProfileDto;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use App\Services\ProfileService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Acciones del usuario final sobre SU PROPIO perfil (self-service, JWT) — a diferencia del resto
 * de Controller/Api, no aplica a clientes OAuth2 de negocio en absoluto (no tienen un User real
 * con contraseña propia detrás del token).
 */
#[OA\Tag(name: 'Profile', description: 'Perfil del usuario autenticado')]
class ProfileController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ScopeAuthorizationService $scopeAuthorizationService,
        private ProfileService $profileService,
    ) {
    }

    #[OA\Put(
        path: '/api/v1/me',
        summary: 'Actualizar mi perfil',
        description: 'Actualiza mi nombre y teléfono. Si soy dueño de una cuenta (cliente/negocio), el teléfono se sincroniza también ahí.',
        tags: ['Profile'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Alejandra Perez Gonzalez'),
                new OA\Property(property: 'phone', type: 'string', example: '+5353026713'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Perfil actualizado')]
    #[OA\Response(response: 400, description: 'name vacío')]
    #[RequireScope('profile.update')]
    #[Route('/me', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($user === null) {
            return $this->error('Esta acción no aplica a este tipo de credencial', 400);
        }

        try {
            $dto = ProfileDto::fromJson($this->getJsonContent($request));
            $this->profileService->updateProfile($user, $dto);

            return $this->success([
                'name' => $user->getName(),
                'phone' => $user->getPhone(),
            ], 'Perfil actualizado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/me/password',
        summary: 'Cambiar mi contraseña',
        description: 'Cambia la contraseña del usuario autenticado, validando la contraseña actual.',
        tags: ['Profile'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['currentPassword', 'newPassword'],
            properties: [
                new OA\Property(property: 'currentPassword', type: 'string', example: 'miPasswordActual'),
                new OA\Property(property: 'newPassword', type: 'string', example: 'miPasswordNueva123'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Contraseña actualizada')]
    #[OA\Response(response: 400, description: 'Contraseña actual incorrecta, o newPassword muy corta')]
    #[RequireScope('profile.update')]
    #[Route('/me/password', name: 'api_profile_change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($user === null) {
            return $this->error('Esta acción no aplica a este tipo de credencial', 400);
        }

        $data = $this->getJsonContent($request);
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->error('La contraseña actual no es correcta', 400);
        }
        if (strlen($newPassword) < 8) {
            return $this->error('La contraseña nueva debe tener al menos 8 caracteres', 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        return $this->success(null, 'Contraseña actualizada');
    }
}
