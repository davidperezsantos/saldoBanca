<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\PaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payment-gateways')]
class PaymentGatewayController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'admin_payment_gateways_page', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('payment_gateway:view');

        return $this->render('admin/payment_gateways.html.twig');
    }

    #[Route('/list', name: 'admin_payment_gateways_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('payment_gateway:view');

        $gateways = $this->entityManager->getRepository(PaymentGateway::class)->findAll();

        $data = array_map(function (PaymentGateway $g) {
            return [
                'id' => $g->getId(),
                'name' => $g->getName(),
                'code' => $g->getCode(),
                'authType' => $g->getAuthType(),
                'config' => $g->getConfig(),
                'status' => $g->getStatus(),
                'isDefault' => $g->isDefault(),
                'notes' => $g->getNotes(),
                'createdAt' => $g->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $gateways);

        return $this->success($data);
    }

    #[Route('', name: 'admin_payment_gateway_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('payment_gateway:create');

        try {
            $this->validateCsrfToken();
            $data = json_decode($request->getContent(), true);

            $gateway = new PaymentGateway();
            $gateway->setName($data['name']);
            $gateway->setCode($data['code']);
            $gateway->setAuthType($data['authType']);
            $gateway->setConfig($data['config'] ?? null);
            $gateway->setStatus($data['status'] ?? 'active');
            $gateway->setIsDefault($data['isDefault'] ?? false);
            $gateway->setNotes($data['notes'] ?? null);

            $this->entityManager->persist($gateway);
            $this->entityManager->flush();

            return $this->success(['id' => $gateway->getId()], 'Gateway created', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'admin_payment_gateway_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('payment_gateway:edit');

        try {
            $this->validateCsrfToken();
            $gateway = $this->entityManager->getRepository(PaymentGateway::class)->find($id);
            if (!$gateway) {
                return $this->error('Gateway not found', 404);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['name'])) $gateway->setName($data['name']);
            if (isset($data['code'])) $gateway->setCode($data['code']);
            if (isset($data['authType'])) $gateway->setAuthType($data['authType']);
            if (isset($data['config'])) $gateway->setConfig($data['config']);
            if (isset($data['status'])) $gateway->setStatus($data['status']);
            if (isset($data['isDefault'])) $gateway->setIsDefault($data['isDefault']);
            if (isset($data['notes'])) $gateway->setNotes($data['notes']);

            $this->entityManager->flush();

            return $this->success(null, 'Gateway updated');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/status', name: 'admin_payment_gateway_status', methods: ['PUT'])]
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('payment_gateway:edit');

        try {
            $this->validateCsrfToken();
            $gateway = $this->entityManager->getRepository(PaymentGateway::class)->find($id);
            if (!$gateway) {
                return $this->error('Gateway not found', 404);
            }

            $data = json_decode($request->getContent(), true);
            $gateway->setStatus($data['status'] ?? 'active');

            $this->entityManager->flush();

            return $this->success(null, 'Status updated');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
