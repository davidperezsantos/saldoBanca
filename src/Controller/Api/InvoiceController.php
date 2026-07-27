<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\DTO\Balance\InvoiceDto;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use App\Services\Balance\InvoiceService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Invoices', description: 'Facturas y cobros')]
class InvoiceController extends BaseController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private ScopeAuthorizationService $scopeAuthorizationService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/invoices',
        summary: 'Listar facturas',
        description: 'Obtiene el listado de facturas con filtros opcionales.',
        tags: ['Invoices'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'query', description: 'Filtrar por ID de cuenta', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado (pending, paid, cancelled, refunded)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'offset', in: 'query', description: 'Desplazamiento', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Lista de facturas',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'i1-...'),
                        new OA\Property(property: 'accountId', type: 'string', example: 'a1-...'),
                        new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                        new OA\Property(property: 'invoiceNumber', type: 'string', example: 'INV-001'),
                        new OA\Property(property: 'invoiceDate', type: 'string', example: '2024-01-01'),
                        new OA\Property(property: 'dueDate', type: 'string', example: '2024-01-31'),
                        new OA\Property(property: 'amount', type: 'string', example: '1000.00'),
                        new OA\Property(property: 'taxAmount', type: 'string', example: '180.00'),
                        new OA\Property(property: 'totalAmount', type: 'string', example: '1180.00'),
                        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'customerName', type: 'string', example: 'Cliente S.A.'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[RequireScope('invoices.read')]
    #[Route('/invoices', name: 'api_invoice_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($selfServiceUser !== null) {
            $filters['accountId'] = (string) $selfServiceUser->getAccount()?->getId();
        } elseif ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        $invoices = $this->invoiceService->listInvoices($filters);
        $data = array_map(fn($i) => $this->invoiceService->serializeForDisplay($i), $invoices);

        return $this->success($data);
    }

    #[OA\Post(
        path: '/api/v1/invoices/payment',
        summary: 'Crear factura/cobro',
        description: 'Registra una nueva factura o cobro. Requiere API Key.',
        tags: ['Invoices'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accountId', description: 'ID de la cuenta', type: 'string', example: 'a1b2c3d4-...'),
                new OA\Property(property: 'invoiceNumber', description: 'Número de factura', type: 'string', example: 'INV-001'),
                new OA\Property(property: 'invoiceDate', description: 'Fecha de factura (Y-m-d)', type: 'string', example: '2024-01-01'),
                new OA\Property(property: 'dueDate', description: 'Fecha de vencimiento (Y-m-d, opcional)', type: 'string', example: '2024-01-31'),
                new OA\Property(property: 'amount', description: 'Monto base', type: 'number', example: 1000.00),
                new OA\Property(property: 'taxAmount', description: 'Monto de impuesto (opcional)', type: 'number', example: 180.00),
                new OA\Property(property: 'totalAmount', description: 'Monto total', type: 'number', example: 1180.00),
                new OA\Property(property: 'currency', description: 'Código de moneda', type: 'string', example: 'USD'),
                new OA\Property(property: 'customerCode', description: 'Código de cliente (opcional)', type: 'string', example: 'C001'),
                new OA\Property(property: 'customerName', description: 'Nombre del cliente (opcional)', type: 'string', example: 'Cliente S.A.'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Factura creada exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Invoice created'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'i1-...'),
                    new OA\Property(property: 'invoiceNumber', type: 'string', example: 'INV-001'),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 401, description: 'Token OAuth2 inválido o ausente')]
    #[OA\Response(response: 403, description: 'Scope insuficiente (requiere "invoices")')]
    #[RequireScope('invoices.create')]
    #[Route('/invoices/payment', name: 'api_invoice_payment', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $dto = InvoiceDto::fromJson($data);
            $invoice = $this->invoiceService->createInvoice($dto);
            return $this->success(
                $this->invoiceService->serializeForDisplay($invoice),
                'Invoice created',
                201
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/invoices/{number}',
        summary: 'Obtener factura por número',
        description: 'Busca y devuelve una factura por su número.',
        tags: ['Invoices'],
    )]
    #[OA\Parameter(name: 'number', in: 'path', description: 'Número de factura', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'accountId', in: 'query', description: 'ID de la cuenta (obligatorio: el número de factura lo asigna el sistema externo y no es único a nivel global)', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Detalle de la factura',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'invoiceNumber', type: 'string', example: 'INV-001'),
                    new OA\Property(property: 'totalAmount', type: 'string', example: '1180.00'),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Invoice not found')]
    #[RequireScope('invoices.read')]
    #[Route('/invoices/{number}', name: 'api_invoice_show', methods: ['GET'])]
    public function show(string $number, Request $request): JsonResponse
    {
        try {
            // El número de factura lo asigna el sistema externo y no es único a nivel global
            // (dos cuentas distintas pueden usar el mismo número) — hay que acotar por cuenta,
            // igual que ya hace InvoiceService::findByAccountAndNumber() en el resto del sistema
            // (ej. AuthorizedService::spend()). Antes este endpoint buscaba sin acotar por cuenta
            // (listInvoices(['limit' => 1]) + comparar en PHP), lo que solo encontraba la factura
            // correcta por coincidencia si era la última creada en todo el sistema.
            $accountId = $request->query->get('accountId');
            if (!$accountId) {
                return $this->error('accountId is required (invoiceNumber is not globally unique)', 422);
            }
            $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
            if ($selfServiceUser !== null && (string) $selfServiceUser->getAccount()?->getId() !== $accountId) {
                return $this->error('No podés consultar facturas de esta cuenta', 403);
            }

            $invoice = $this->invoiceService->findByAccountAndNumber($accountId, $number);
            if (!$invoice) {
                return $this->error('Invoice not found', 404);
            }

            return $this->success($this->invoiceService->serializeForDisplay($invoice, detailed: true));
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/invoices/{id}/pay',
        summary: 'Pagar factura',
        description: 'Procesa el pago de una factura pendiente.',
        tags: ['Invoices'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la factura', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['pinCode'],
            properties: [
                new OA\Property(property: 'pinCode', description: 'PIN vigente de la cuenta que paga', type: 'string', example: '4821'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Invoice paid')]
    #[OA\Response(response: 400, description: 'Error al pagar factura, PIN inválido, etc.')]
    #[RequireScope('invoices.pay')]
    #[Route('/invoices/{id}/pay', name: 'api_invoice_pay', methods: ['PUT'])]
    public function pay(string $id, Request $request): JsonResponse
    {
        $data = $this->getJsonContent($request);
        $pinCode = $data['pinCode'] ?? null;
        if (!$pinCode) {
            return $this->error('pinCode is required', 400);
        }

        try {
            $existing = $this->invoiceService->getInvoice($id);
            if (!$existing) {
                return $this->error('Invoice not found', 404);
            }
            if (!$this->scopeAuthorizationService->selfServiceOwnsAccount($existing->getAccount())) {
                return $this->error('Invoice not found', 404);
            }

            $invoice = $this->invoiceService->processPayment($id, $pinCode);
            return $this->success([
                'id' => $invoice->getId(),
                'status' => $invoice->getStatus(),
                'paymentDate' => $invoice->getPaymentDate()?->format('Y-m-d'),
            ], 'Invoice paid');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/invoices/{id}/cancel',
        summary: 'Cancelar factura',
        description: 'Cancela una factura pendiente.',
        tags: ['Invoices'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la factura', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Invoice cancelled')]
    #[OA\Response(response: 400, description: 'Error al cancelar factura')]
    #[RequireScope('invoices.cancel')]
    #[Route('/invoices/{id}/cancel', name: 'api_invoice_cancel', methods: ['PUT'])]
    public function cancel(string $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->cancelPayment($id);
            return $this->success([
                'id' => $invoice->getId(),
                'status' => $invoice->getStatus(),
            ], 'Invoice cancelled');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/invoices/{id}/refund',
        summary: 'Reembolsar factura',
        description: 'Procesa el reembolso de una factura pagada.',
        tags: ['Invoices'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la factura', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Invoice refunded')]
    #[OA\Response(response: 400, description: 'Error al reembolsar factura')]
    #[RequireScope('invoices.refund')]
    #[Route('/invoices/{id}/refund', name: 'api_invoice_refund', methods: ['PUT'])]
    public function refund(string $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->refundPayment($id);
            return $this->success([
                'id' => $invoice->getId(),
                'status' => $invoice->getStatus(),
            ], 'Invoice refunded');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/invoices/summary/{accountId}',
        summary: 'Resumen de facturas',
        description: 'Obtiene un resumen de facturas por cuenta (pendientes, pagadas, vencidas, montos totales).',
        tags: ['Invoices'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'path', description: 'ID de la cuenta', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Resumen de facturas',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'totalPending', type: 'integer', example: 5),
                    new OA\Property(property: 'totalPaid', type: 'integer', example: 10),
                    new OA\Property(property: 'totalOverdue', type: 'integer', example: 2),
                    new OA\Property(property: 'amountPending', type: 'string', example: '5000.00'),
                    new OA\Property(property: 'amountPaid', type: 'string', example: '15000.00'),
                ], type: 'object'),
            ]
        )
    )]
    #[RequireScope('invoices.read')]
    #[Route('/invoices/summary/{accountId}', name: 'api_invoice_summary', methods: ['GET'])]
    public function summary(string $accountId): JsonResponse
    {
        try {
            $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
            if ($selfServiceUser !== null
                && (string) $selfServiceUser->getAccount()?->getId() !== $accountId
            ) {
                return $this->error('No podés consultar el resumen de esta cuenta', 403);
            }
            $summary = $this->invoiceService->getInvoiceSummary($accountId);
            return $this->success($summary);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
