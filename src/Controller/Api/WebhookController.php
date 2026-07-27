<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Exception\WebhookAuthenticationException;
use App\Services\Balance\WebhookService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Webhooks', description: 'Webhooks de pasarelas de pago')]
class WebhookController extends BaseController
{
    public function __construct(
        private WebhookService $webhookService,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/webhooks/recharges/{gatewayCode}',
        summary: 'Webhook de recarga entrante',
        description: 'Recibe la notificación de una pasarela de pago externa. No usa OAuth2: la ' .
            'autenticación es la firma HMAC-SHA256 del cuerpo crudo en el header X-Webhook-Signature, ' .
            'validada contra el secreto configurado para la pasarela. Idempotente: reentregas del ' .
            'mismo (externalSystem, referenceNumber) no duplican el crédito.',
        tags: ['Webhooks'],
    )]
    #[OA\Parameter(name: 'gatewayCode', in: 'path', description: 'Código de la pasarela (PaymentGateway.code)', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Webhook procesado (o ya procesado antes, idempotente)')]
    #[OA\Response(response: 400, description: 'Payload inválido o error al procesar la recarga')]
    #[OA\Response(response: 401, description: 'Pasarela desconocida/inactiva o firma inválida')]
    #[Route('/webhooks/recharges/{gatewayCode}', name: 'api_webhook_recharge', methods: ['POST'])]
    public function recharge(string $gatewayCode, Request $request): JsonResponse
    {
        $signature = $request->headers->get('X-Webhook-Signature');

        try {
            $recharge = $this->webhookService->handleRechargeWebhook($gatewayCode, $request->getContent(), $signature);

            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Webhook processed');
        } catch (WebhookAuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
