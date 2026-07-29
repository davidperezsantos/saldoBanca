<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\PaymentGateway;
use App\Security\Attribute\RequireScope;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Payment Gateways', description: 'Pasarelas de pago')]
class PaymentGatewayController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/admin/payment-gateways',
        summary: 'Listar pasarelas de pago',
        description: 'Obtiene el listado de pasarelas de pago disponibles.',
        tags: ['Payment Gateways'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Lista de pasarelas de pago',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'pg1-...'),
                        new OA\Property(property: 'name', type: 'string', example: 'PayPal'),
                        new OA\Property(property: 'code', type: 'string', example: 'PAYPAL'),
                        new OA\Property(property: 'authType', type: 'string', example: 'token'),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'isDefault', type: 'boolean', example: false),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[RequireScope('payment_gateways.read')]
    #[Route('/admin/payment-gateways', name: 'api_payment_gateway_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $gateways = $this->entityManager->getRepository(PaymentGateway::class)->findAll();
        $data = array_map(fn($g) => [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'code' => $g->getCode(),
            'authType' => $g->getAuthType(),
            'status' => $g->getStatus(),
            'isDefault' => $g->isDefault(),
            'createdAt' => $g->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $gateways);
        return $this->success($data);
    }
}
