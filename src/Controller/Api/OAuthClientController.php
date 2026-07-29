<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestión de clientes OAuth2 (negocios externos) desde mobile/ — espejo JSON de
 * Controller/Admin/OAuthClientController (panel Twig, sesión de cookie, inalcanzable desde JWT).
 * Misma lógica de negocio, sin capa de Service nueva — su par Twig tampoco la tiene.
 */
#[OA\Tag(name: 'OAuth Clients', description: 'Gestión de clientes OAuth2 (requiere scope oauth_clients.*)')]
class OAuthClientController extends BaseController
{
    public function __construct(
        private ClientManagerInterface $clientManager,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/admin/oauth-clients',
        summary: 'Listar clientes OAuth2',
        tags: ['OAuth Clients'],
    )]
    #[OA\Response(response: 200, description: 'Lista de clientes OAuth2')]
    #[RequireScope('oauth_clients.read')]
    #[Route('/admin/oauth-clients', name: 'api_admin_oauth_clients_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $clients = $this->clientManager->list(null);
        $data = array_map(fn(Client $c) => [
            'identifier' => $c->getIdentifier(),
            'name' => $c->getName(),
            // El secreto real NO viaja en el listado — solo al crear (una vez) o vía
            // GET .../secret (ver revealSecret()).
            'secret' => $c->getSecret() ? '••••••••' : null,
            'redirectUris' => array_map(fn(RedirectUri $r) => (string) $r, $c->getRedirectUris()),
            'grants' => array_map(fn(Grant $g) => (string) $g, $c->getGrants()),
            'scopes' => array_map(fn(Scope $s) => (string) $s, $c->getScopes()),
            'active' => $c->isActive(),
            'allowPlainTextPkce' => $c->isPlainTextPkceAllowed(),
            'confidential' => $c->isConfidential(),
        ], $clients);

        return $this->success($data);
    }

    #[OA\Get(
        path: '/api/v1/admin/oauth-clients/{identifier}/secret',
        summary: 'Revelar el secreto de un cliente OAuth2',
        tags: ['OAuth Clients'],
    )]
    #[OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Secreto del cliente')]
    #[OA\Response(response: 404, description: 'Client not found')]
    #[RequireScope('oauth_clients.read')]
    #[Route('/admin/oauth-clients/{identifier}/secret', name: 'api_admin_oauth_client_secret', methods: ['GET'])]
    public function revealSecret(string $identifier): JsonResponse
    {
        $client = $this->clientManager->find($identifier);
        if (!$client) {
            return $this->error('Client not found', 404);
        }

        return $this->success(['secret' => $client->getSecret()]);
    }

    #[OA\Post(
        path: '/api/v1/admin/oauth-clients',
        summary: 'Crear cliente OAuth2',
        tags: ['OAuth Clients'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Negocio Acme'),
                new OA\Property(property: 'public', type: 'boolean', description: 'Cliente público (sin secreto), ej. SPA', example: false),
                new OA\Property(property: 'allowPlainTextPkce', type: 'boolean', example: false),
                new OA\Property(property: 'redirectUris', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'grants', type: 'array', items: new OA\Items(type: 'string', example: 'client_credentials')),
                new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string', example: 'accounts.read')),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Cliente creado (incluye el secreto en texto plano, única vez)')]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[RequireScope('oauth_clients.create')]
    #[Route('/admin/oauth-clients', name: 'api_admin_oauth_client_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $body = $this->getJsonContent($request);
            $name = $body['name'] ?? '';
            $identifier = hash('md5', random_bytes(16));
            $isPublic = !empty($body['public']);

            if ($isPublic && !empty($body['secret'])) {
                return $this->error('Public client cannot have a secret', 400);
            }

            $secret = $isPublic ? null : hash('sha512', random_bytes(32));

            $client = new Client($name, $identifier, $secret);
            $client->setActive(true);
            $client->setAllowPlainTextPkce(!empty($body['allowPlainTextPkce']));

            if (!empty($body['redirectUris'])) {
                $uris = array_map(fn(string $u) => new RedirectUri($u), $body['redirectUris']);
                $client->setRedirectUris(...$uris);
            }
            if (!empty($body['grants'])) {
                $grants = array_map(fn(string $g) => new Grant($g), $body['grants']);
                $client->setGrants(...$grants);
            }
            if (!empty($body['scopes'])) {
                $scopes = array_map(fn(string $s) => new Scope($s), $body['scopes']);
                $client->setScopes(...$scopes);
            }

            $this->clientManager->save($client);

            return $this->success([
                'identifier' => $client->getIdentifier(),
                'name' => $client->getName(),
                'secret' => $client->getSecret(),
            ], 'OAuth client created', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(
        path: '/api/v1/admin/oauth-clients/{identifier}',
        summary: 'Actualizar cliente OAuth2',
        tags: ['OAuth Clients'],
    )]
    #[OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Cliente actualizado')]
    #[OA\Response(response: 404, description: 'Client not found')]
    #[RequireScope('oauth_clients.update')]
    #[Route('/admin/oauth-clients/{identifier}', name: 'api_admin_oauth_client_update', methods: ['PUT'])]
    public function update(string $identifier, Request $request): JsonResponse
    {
        try {
            $client = $this->clientManager->find($identifier);
            if (!$client) {
                return $this->error('Client not found', 404);
            }

            $body = $this->getJsonContent($request);

            if (isset($body['name'])) {
                $client->setName($body['name']);
            }
            if (isset($body['active'])) {
                $client->setActive((bool) $body['active']);
            }
            if (isset($body['allowPlainTextPkce'])) {
                $client->setAllowPlainTextPkce((bool) $body['allowPlainTextPkce']);
            }
            if (isset($body['redirectUris'])) {
                $uris = array_map(fn(string $u) => new RedirectUri($u), $body['redirectUris']);
                $client->setRedirectUris(...$uris);
            }
            if (isset($body['grants'])) {
                $grants = array_map(fn(string $g) => new Grant($g), $body['grants']);
                $client->setGrants(...$grants);
            }
            if (isset($body['scopes'])) {
                $scopes = array_map(fn(string $s) => new Scope($s), $body['scopes']);
                $client->setScopes(...$scopes);
            }

            $this->clientManager->save($client);

            return $this->success(null, 'OAuth client updated');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Delete(
        path: '/api/v1/admin/oauth-clients/{identifier}',
        summary: 'Eliminar cliente OAuth2',
        tags: ['OAuth Clients'],
    )]
    #[OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Cliente eliminado')]
    #[OA\Response(response: 404, description: 'Client not found')]
    #[RequireScope('oauth_clients.delete')]
    #[Route('/admin/oauth-clients/{identifier}', name: 'api_admin_oauth_client_delete', methods: ['DELETE'])]
    public function delete(string $identifier): JsonResponse
    {
        $client = $this->clientManager->find($identifier);
        if (!$client) {
            return $this->error('Client not found', 404);
        }

        $this->clientManager->remove($client);

        return $this->success(null, 'OAuth client deleted');
    }

    #[OA\Put(
        path: '/api/v1/admin/oauth-clients/{identifier}/status',
        summary: 'Activar/desactivar cliente OAuth2',
        tags: ['OAuth Clients'],
    )]
    #[OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'active', type: 'boolean'),
        ])
    )]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    #[OA\Response(response: 404, description: 'Client not found')]
    #[RequireScope('oauth_clients.status')]
    #[Route('/admin/oauth-clients/{identifier}/status', name: 'api_admin_oauth_client_status', methods: ['PUT'])]
    public function toggleStatus(string $identifier, Request $request): JsonResponse
    {
        $client = $this->clientManager->find($identifier);
        if (!$client) {
            return $this->error('Client not found', 404);
        }

        $body = $this->getJsonContent($request);
        $active = (bool) ($body['active'] ?? !$client->isActive());
        $client->setActive($active);
        $this->clientManager->save($client);

        return $this->success(['active' => $client->isActive()], 'Status updated');
    }
}
