<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
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
}
