<?php

namespace App\EventListener;

use App\Entity\Balance\Account;
use App\Entity\Balance\TransactionLog;
use App\Repository\Balance\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Registra en TransactionLog toda mutación (POST/PUT/PATCH/DELETE) bajo /api/v1, sin depender de
 * que cada servicio lo llame a mano. Se hace en un listener genérico en vez de en cada controlador
 * para no tener que acordarse de repetirlo endpoint por endpoint.
 *
 * account queda null cuando la ruta no resuelve a una única cuenta identificable (ver
 * TransactionLog.account, ahora nullable) — igual se registra el resto (actor, ip, payload,
 * resultado), que ya es valioso para auditoría aunque no se pueda enlazar a una cuenta puntual.
 */
class ApiTransactionLogListener
{
    private const SKIP_PREFIXES = ['/api/v1/login', '/api/v1/register'];
    private const SENSITIVE_KEYS = ['password', 'pinCode', 'pin_code', 'client_secret', 'secret', 'token'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountRepository $accountRepository,
        private Security $security,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/v1/')) {
            return;
        }

        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // El logging es best-effort: nunca debe poder convertir una respuesta de error normal
        // (o exitosa) en un 500 propio. Si el EntityManager de este request ya quedó cerrado por
        // una excepción anterior en el mismo ciclo (ej. un flush fallido dentro de una transacción),
        // no hay nada seguro que loggear — se omite en vez de intentar y volver a explotar.
        if (!$this->entityManager->isOpen()) {
            return;
        }

        try {
            $response = $event->getResponse();
            $requestData = json_decode($request->getContent(), true);
            $requestData = is_array($requestData) ? $requestData : [];
            $responseData = json_decode($response->getContent(), true);
            $responseData = is_array($responseData) ? $responseData : [];

            $log = new TransactionLog();
            $log->setAccount($this->resolveAccount($request, $requestData, $responseData));
            $log->setAction($request->attributes->get('_route') ?? $path);
            $log->setPerformedBy($this->security->getUser()?->getUserIdentifier());
            $log->setIpAddress($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
            $log->setRequestData($this->redact($requestData));
            $log->setResponseData($responseData);
            $log->setStatus($response->isSuccessful() ? 'success' : 'error');
            if (!$response->isSuccessful()) {
                $log->setErrorMessage(is_string($responseData['message'] ?? null) ? $responseData['message'] : null);
            }

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            // No dejar que un fallo al auditar tumbe la respuesta real de la API.
        }
    }

    private function resolveAccount(Request $request, array $requestData, array $responseData): ?Account
    {
        $accountId = $request->attributes->get('accountId')
            ?? $requestData['accountId']
            ?? $responseData['data']['accountId']
            ?? null;

        if (!$accountId) {
            return null;
        }

        return $this->accountRepository->find($accountId);
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (in_array($key, self::SENSITIVE_KEYS, true)) {
                $data[$key] = '***redacted***';
            }
        }

        return $data;
    }
}
