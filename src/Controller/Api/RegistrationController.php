<?php

namespace App\Controller\Api;

use App\Http\ApiResponse;
use App\Services\RegistrationService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Auth', description: 'Autenticación de usuarios')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private RegistrationService $registrationService,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/register/client',
        summary: 'Registrar cliente',
        description: 'Crea un cliente con cuenta y usuario automáticamente.',
        tags: ['Auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                new OA\Property(property: 'email', type: 'string', example: 'juan@ejemplo.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+584141234567'),
                new OA\Property(property: 'password', type: 'string', example: 'miPassword123'),
                new OA\Property(property: 'documentType', type: 'string', example: 'V'),
                new OA\Property(property: 'documentNumber', type: 'string', example: '12345678'),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Cliente registrado exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Client registered successfully'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'user', properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'name', type: 'string'),
                    ], type: 'object'),
                    new OA\Property(property: 'account', properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'accountNumber', type: 'string'),
                    ], type: 'object'),
                ], type: 'object'),
            ],
        ),
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 409, description: 'Email already exists')]
    #[Route('/register/client', name: 'api_register_client', methods: ['POST'])]
    public function registerClient(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $result = $this->registrationService->registerClient($data);

            $token = $this->jwtManager->create($result['user']);

            return ApiResponse::success([
                'token' => $token,
                'user' => [
                    'id' => $result['user']->getId(),
                    'username' => $result['user']->getUsername(),
                    'email' => $result['user']->getEmail(),
                    'name' => $result['user']->getName(),
                ],
                'account' => [
                    'id' => $result['account']->getId(),
                    'accountNumber' => $result['account']->getAccountNumber(),
                ],
            ], 'Client registered successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::fromException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/register/business',
        summary: 'Registrar negocio',
        description: 'Crea una cuenta de negocio sin usuario. Un operador deberá aprobarla después.',
        tags: ['Auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['businessName', 'documentType', 'documentNumber'],
            properties: [
                new OA\Property(property: 'businessName', type: 'string', example: 'Mi Empresa S.A.'),
                new OA\Property(property: 'email', type: 'string', example: 'info@miempresa.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+582121234567'),
                new OA\Property(property: 'documentType', type: 'string', example: 'J'),
                new OA\Property(property: 'documentNumber', type: 'string', example: 'J-123456789'),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Negocio registrado exitosamente, pendiente de aprobación',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Business registered, pending approval'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'account', properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'accountNumber', type: 'string'),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                    ], type: 'object'),
                ], type: 'object'),
            ],
        ),
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[Route('/register/business', name: 'api_register_business', methods: ['POST'])]
    public function registerBusiness(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $account = $this->registrationService->registerBusiness($data);

            return ApiResponse::success([
                'account' => [
                    'id' => $account->getId(),
                    'accountNumber' => $account->getAccountNumber(),
                    'status' => $account->getStatus(),
                ],
            ], 'Business registered, pending approval', 201);
        } catch (\Exception $e) {
            return ApiResponse::fromException($e);
        }
    }
}
