<?php

namespace App\Services\Balance;

use App\Entity\Balance\PaymentGateway;
use App\Entity\Balance\Recharge;
use App\Entity\Balance\WebhookEvent;
use App\Exception\ValidationException;
use App\Exception\WebhookAuthenticationException;
use App\Repository\Balance\PaymentGatewayRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

class WebhookService extends BaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private PaymentGatewayRepository $gatewayRepository,
        private RechargeService $rechargeService,
    ) {
        parent::__construct($entityManager);
    }

    /**
     * Procesa un webhook de recarga entrante: valida la pasarela y su firma HMAC, deja registro
     * del evento (éxito o no) y delega en RechargeService::processWebhookRecharge() para la
     * creación/acreditación idempotente. Nunca confía en el payload sin verificar la firma primero.
     */
    public function handleRechargeWebhook(string $gatewayCode, string $rawBody, ?string $signature): Recharge
    {
        $gateway = $this->gatewayRepository->findByCode($gatewayCode);

        if (!$gateway || $gateway->getStatus() !== 'active') {
            $this->logEvent($gatewayCode, null, null, false, 'unknown_gateway', null, 'Gateway not found or inactive');
            throw new WebhookAuthenticationException("Unknown or inactive gateway: {$gatewayCode}");
        }

        $secret = $gateway->getConfig()['webhook_secret'] ?? null;
        if (!$secret) {
            $this->logEvent($gatewayCode, $gateway, null, false, 'misconfigured', null, 'Gateway has no webhook_secret configured');
            throw new WebhookAuthenticationException("Gateway {$gatewayCode} has no webhook secret configured");
        }

        $signatureValid = $signature !== null && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
        if (!$signatureValid) {
            $this->logEvent($gatewayCode, $gateway, null, false, 'rejected_signature', null, 'Invalid or missing signature');
            throw new WebhookAuthenticationException('Invalid webhook signature');
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            $this->logEvent($gatewayCode, $gateway, null, true, 'invalid_payload', null, 'Malformed JSON payload');
            throw new ValidationException('Malformed JSON payload');
        }

        $data['externalSystem'] = $gateway->getCode();

        $referenceNumber = $data['referenceNumber'] ?? null;
        if ($referenceNumber) {
            $existing = $this->rechargeService->findByExternalReference($gateway->getCode(), $referenceNumber);
            if ($existing) {
                $this->logEvent($gatewayCode, $gateway, $data, true, 'duplicate', $existing, null);
                return $existing;
            }
        }

        try {
            $recharge = $this->rechargeService->processWebhookRecharge($data);
        } catch (\Exception $e) {
            $this->logEvent($gatewayCode, $gateway, $data, true, 'error', null, $e->getMessage());
            throw $e;
        }

        $this->logEvent($gatewayCode, $gateway, $data, true, 'processed', $recharge, null);

        return $recharge;
    }

    private function logEvent(
        string $gatewayCode,
        ?PaymentGateway $gateway,
        ?array $payload,
        bool $signatureValid,
        string $status,
        ?Recharge $recharge,
        ?string $errorMessage
    ): void {
        $event = new WebhookEvent();
        $event->setGatewayCode($gatewayCode);
        $event->setGateway($gateway);
        $event->setPayload($payload);
        $event->setSignatureValid($signatureValid);
        $event->setStatus($status);
        $event->setRecharge($recharge);
        $event->setErrorMessage($errorMessage);

        $this->persist($event);
        $this->flush();
    }
}
