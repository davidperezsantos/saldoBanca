<?php

namespace App\Controller;

use App\Services\Balance\BusinessReconciliationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Página pública (sin login) donde el negocio aprueba o rechaza una conciliación desde el link que
 * recibe por WhatsApp — mismo patrón token-en-URL que LoginController::confirm() (reset-password).
 */
class ReconciliationApprovalController extends BaseController
{
    public function __construct(
        private BusinessReconciliationService $reconciliationService,
    ) {
    }

    #[Route('/conciliacion/{token}', name: 'reconciliation_public_show', methods: ['GET'])]
    public function show(string $token): Response
    {
        $reconciliation = $this->reconciliationService->getByToken($token);
        if (!$reconciliation) {
            return $this->render('reconciliation/invalid.html.twig');
        }

        $this->reconciliationService->backfillPayoutSplitIfMissing($reconciliation);

        $verifiedAt = $reconciliation->getApprovalPinVerifiedAt();
        $canAct = $verifiedAt !== null
            && $verifiedAt > new \DateTimeImmutable('-' . BusinessReconciliationService::PIN_VERIFIED_TTL_MINUTES . ' minutes');

        $pinExpiresAt = $reconciliation->getApprovalPinExpiresAt();
        $needsPinVerification = !$canAct
            && $reconciliation->getApprovalPinHash() !== null
            && $pinExpiresAt !== null
            && $pinExpiresAt > new \DateTimeImmutable();

        $needsPinRequest = !$canAct && !$needsPinVerification;

        $payoutAccounts = $canAct ? $this->reconciliationService->getPayoutAccountsForApproval($reconciliation) : null;

        return $this->render('reconciliation/approve.html.twig', [
            'token' => $token,
            'reconciliation' => $reconciliation,
            'needsPinRequest' => $needsPinRequest,
            'needsPinVerification' => $needsPinVerification,
            'canAct' => $canAct,
            'payoutAccounts' => $payoutAccounts,
        ]);
    }

    #[Route('/conciliacion/{token}/request-pin', name: 'reconciliation_public_request_pin', methods: ['POST'])]
    public function requestPin(string $token): Response
    {
        try {
            $this->reconciliationService->requestApprovalPin($token);
            $this->addFlash('success', 'Código enviado a tu WhatsApp.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('reconciliation_public_show', ['token' => $token]);
    }

    #[Route('/conciliacion/{token}/verify-pin', name: 'reconciliation_public_verify_pin', methods: ['POST'])]
    public function verifyPin(string $token, Request $request): Response
    {
        $pin = trim((string) $request->request->get('pin'));

        try {
            $this->reconciliationService->verifyApprovalPin($token, $pin);
            $this->addFlash('success', 'Código verificado, ya puedes aprobar o rechazar.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('reconciliation_public_show', ['token' => $token]);
    }

    #[Route('/conciliacion/{token}/approve', name: 'reconciliation_public_approve', methods: ['POST'])]
    public function approve(string $token, Request $request): Response
    {
        $approverName = trim((string) $request->request->get('approverName'));
        $payoutAccountBaseId = $request->request->get('payoutAccountBaseId') ?: null;
        $payoutAccountSecondaryId = $request->request->get('payoutAccountSecondaryId') ?: null;

        if (!$approverName) {
            $this->addFlash('error', 'Debes indicar tu nombre para aprobar.');
            return $this->redirectToRoute('reconciliation_public_show', ['token' => $token]);
        }

        try {
            $this->reconciliationService->approveByBusiness($token, $approverName, $payoutAccountBaseId, $payoutAccountSecondaryId);
            $this->addFlash('success', 'Conciliación aprobada. Gracias.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('reconciliation_public_show', ['token' => $token]);
    }

    #[Route('/conciliacion/{token}/reject', name: 'reconciliation_public_reject', methods: ['POST'])]
    public function reject(string $token, Request $request): Response
    {
        $approverName = trim((string) $request->request->get('approverName'));
        $reason = $request->request->get('reason');

        if (!$approverName) {
            $this->addFlash('error', 'Debes indicar tu nombre para rechazar.');
            return $this->redirectToRoute('reconciliation_public_show', ['token' => $token]);
        }

        try {
            $this->reconciliationService->rejectByBusiness($token, $approverName, $reason);
            $this->addFlash('success', 'Conciliación rechazada.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('reconciliation_public_show', ['token' => $token]);
    }
}
