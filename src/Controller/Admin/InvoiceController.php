<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\InvoiceDto;
use App\Services\Balance\InvoiceService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoices')]
class InvoiceController extends BaseController
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {
    }

    #[Route('', name: 'admin_invoices_page')]
    public function invoicesPage(): Response
    {
        $this->denyAccessUnlessGranted('invoices:view');

        return $this->render('admin/invoices.html.twig');
    }

    #[Route('/list', name: 'admin_invoice_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('invoices:view');

        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];

        if ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }

        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        $invoices = $this->invoiceService->listInvoices($filters);

        $data = array_map(function ($invoice) {
            return [
                'id' => $invoice->getId(),
                'accountId' => $invoice->getAccount()->getId(),
                'accountNumber' => $invoice->getAccount()->getAccountNumber(),
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'invoiceDate' => $invoice->getInvoiceDate()->format('Y-m-d'),
                'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                'amount' => $invoice->getAmount(),
                'taxAmount' => $invoice->getTaxAmount(),
                'totalAmount' => $invoice->getTotalAmount(),
                'currency' => $invoice->getCurrency(),
                'status' => $invoice->getStatus(),
                'paymentDate' => $invoice->getPaymentDate()?->format('Y-m-d'),
                'customerCode' => $invoice->getCustomerCode(),
                'customerName' => $invoice->getCustomerName(),
                'createdAt' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $invoices);

        return $this->success($data);
    }

    #[Route('', name: 'admin_invoice_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('invoices:create');

        try {
            $data = $this->getJsonContent($request);
            $dto = InvoiceDto::fromJson($data);
            $invoice = $this->invoiceService->createInvoice($dto);

            return $this->success([
                'id' => $invoice->getId(),
                'invoiceNumber' => $invoice->getInvoiceNumber(),
                'totalAmount' => $invoice->getTotalAmount(),
                'status' => $invoice->getStatus(),
            ], 'Invoice created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'admin_invoice_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('invoices:details');

        $invoice = $this->invoiceService->getInvoice($id);

        if (!$invoice) {
            return $this->error('Invoice not found', 404);
        }

        return $this->success([
            'id' => $invoice->getId(),
            'accountId' => $invoice->getAccount()->getId(),
            'accountNumber' => $invoice->getAccount()->getAccountNumber(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
            'invoiceDate' => $invoice->getInvoiceDate()->format('Y-m-d'),
            'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
            'amount' => $invoice->getAmount(),
            'taxAmount' => $invoice->getTaxAmount(),
            'totalAmount' => $invoice->getTotalAmount(),
            'currency' => $invoice->getCurrency(),
            'status' => $invoice->getStatus(),
            'paymentDate' => $invoice->getPaymentDate()?->format('Y-m-d'),
            'externalRef' => $invoice->getExternalRef(),
            'externalSystem' => $invoice->getExternalSystem(),
            'customerCode' => $invoice->getCustomerCode(),
            'customerName' => $invoice->getCustomerName(),
            'notes' => $invoice->getNotes(),
            'createdAt' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}/pay', name: 'admin_invoice_pay', methods: ['PUT'])]
    public function pay(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('invoices:pay');

        try {
            $invoice = $this->invoiceService->processPayment($id);

            return $this->success([
                'id' => $invoice->getId(),
                'status' => $invoice->getStatus(),
                'paymentDate' => $invoice->getPaymentDate()?->format('Y-m-d'),
            ], 'Invoice paid successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/cancel', name: 'admin_invoice_cancel', methods: ['PUT'])]
    public function cancel(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('invoices:cancel');

        try {
            $invoice = $this->invoiceService->cancelPayment($id);

            return $this->success([
                'id' => $invoice->getId(),
                'status' => $invoice->getStatus(),
            ], 'Invoice cancelled successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/refund', name: 'admin_invoice_refund', methods: ['PUT'])]
    public function refund(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('invoices:refund');

        try {
            $invoice = $this->invoiceService->refundPayment($id);

            return $this->success([
                'id' => $invoice->getId(),
                'status' => $invoice->getStatus(),
            ], 'Invoice refunded successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/summary/{accountId}', name: 'admin_invoice_summary', methods: ['GET'])]
    public function summary(string $accountId): JsonResponse
    {
        $this->denyAccessUnlessGranted('invoices:summary');

        try {
            $summary = $this->invoiceService->getInvoiceSummary($accountId);

            return $this->success($summary);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

}
