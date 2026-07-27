<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Autoedición del propio perfil desde el bloque de usuario del sidebar (admin_layout.html.twig) —
 * solo el nombre completo es editable ahí. username/email quedan de solo lectura a propósito:
 * username es el identificador de login (UsernameGenerator + unicidad), cambiarlo desde acá sin
 * las mismas validaciones rompería ese contrato.
 */
#[Route('/profile')]
class ProfileController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/name', name: 'admin_profile_update_name', methods: ['PUT'])]
    public function updateName(Request $request): JsonResponse
    {
        try {
            $this->validateCsrfToken();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        $data = $this->getJsonContent($request);
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            return $this->error('El nombre es requerido', 422);
        }

        $user->setName($name);
        $this->entityManager->flush();

        return $this->success(['name' => $user->getName()], 'Nombre actualizado');
    }
}
