<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\ChangePasswordDto;
use App\DTO\ProfileDto;
use App\Entity\User;
use App\Services\ProfileService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profile')]
class ProfileController extends BaseController
{
    public function __construct(
        private ProfileService $profileService,
    ) {
    }

    #[Route('/name', name: 'admin_profile_update_name', methods: ['PUT'])]
    public function updateName(Request $request): JsonResponse
    {
        try {
            $this->validateCsrfToken();

            /** @var User $user */
            $user = $this->getUser();
            $dto = ProfileDto::fromJson($this->getJsonContent($request));

            $this->profileService->updateName($user, $dto);

            return $this->success(['name' => $user->getName()], 'Nombre actualizado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/password', name: 'admin_profile_change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $this->validateCsrfToken();

            /** @var User $user */
            $user = $this->getUser();
            $dto = ChangePasswordDto::fromJson($this->getJsonContent($request));

            $this->profileService->changePassword($user, $dto);

            return $this->success(null, 'Contraseña actualizada');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
