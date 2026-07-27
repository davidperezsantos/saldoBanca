<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/oauth-clients')]
class OAuthClientController extends BaseController
{
    public function __construct(
        private ClientManagerInterface $clientManager,
    ) {
    }

    #[Route('', name: 'admin_oauth_clients_page', methods: ['GET'])]
    public function page(): Response
    {
        $this->denyAccessUnlessGranted('oauth_clients:view');

        return $this->render('admin/oauth_clients.html.twig');
    }

    #[Route('/list', name: 'admin_oauth_client_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $this->denyAccessUnlessGranted('oauth_clients:view');

        $clients = $this->clientManager->list(null);
        $data = array_map(fn(Client $c) => [
            'identifier' => $c->getIdentifier(),
            'name' => $c->getName(),
            // El secreto real NO viaja en el listado — solo al crear el cliente (una vez) o bajo
            // demanda vía GET .../secret (ver revealSecret()). Acá solo se indica si tiene uno.
            'secret' => $c->getSecret() ? '••••••••' : null,
            'redirectUris' => array_map(fn(RedirectUri $r) => (string) $r, $c->getRedirectUris()),
            'grants' => array_map(fn(Grant $g) => (string) $g, $c->getGrants()),
            'scopes' => array_map(fn(Scope $s) => (string) $s, $c->getScopes()),
            'active' => $c->isActive(),
            'allowPlainTextPkce' => $c->isPlainTextPkceAllowed(),
            'confidential' => $c->isConfidential(),
        ], $clients);

        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('/{identifier}/secret', name: 'admin_oauth_client_secret', methods: ['GET'])]
    public function revealSecret(string $identifier): JsonResponse
    {
        $this->denyAccessUnlessGranted('oauth_clients:view');

        $client = $this->clientManager->find($identifier);
        if (!$client) {
            return $this->json(['success' => false, 'message' => 'Client not found'], 404);
        }

        return $this->json(['success' => true, 'data' => ['secret' => $client->getSecret()]]);
    }

    #[Route('', name: 'admin_oauth_client_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('oauth_clients:create');

        try {
            $this->validateCsrfToken();
            $body = json_decode($request->getContent(), true);
            $name = $body['name'] ?? '';
            $identifier = hash('md5', random_bytes(16));
            $isPublic = !empty($body['public']);

            if ($isPublic && !empty($body['secret'])) {
                return $this->json(['success' => false, 'message' => 'Public client cannot have a secret'], 400);
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

            return $this->json([
                'success' => true,
                'message' => 'OAuth client created',
                'data' => [
                    'identifier' => $client->getIdentifier(),
                    'name' => $client->getName(),
                    'secret' => $client->getSecret(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/{identifier}', name: 'admin_oauth_client_update', methods: ['PUT'])]
    public function update(string $identifier, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('oauth_clients:edit');

        try {
            $this->validateCsrfToken();
            $client = $this->clientManager->find($identifier);
            if (!$client) {
                return $this->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $body = json_decode($request->getContent(), true);

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

            return $this->json(['success' => true, 'message' => 'OAuth client updated']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/{identifier}', name: 'admin_oauth_client_delete', methods: ['DELETE'])]
    public function delete(string $identifier): JsonResponse
    {
        $this->denyAccessUnlessGranted('oauth_clients:delete');

        try {
            $this->validateCsrfToken();
            $client = $this->clientManager->find($identifier);
            if (!$client) {
                return $this->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $this->clientManager->remove($client);

            return $this->json(['success' => true, 'message' => 'OAuth client deleted']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/{identifier}/status', name: 'admin_oauth_client_status', methods: ['PUT'])]
    public function toggleStatus(string $identifier, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('oauth_clients:status');

        try {
            $this->validateCsrfToken();
            $client = $this->clientManager->find($identifier);
            if (!$client) {
                return $this->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $body = json_decode($request->getContent(), true);
            $active = (bool) ($body['active'] ?? !$client->isActive());
            $client->setActive($active);
            $this->clientManager->save($client);

            return $this->json(['success' => true, 'message' => 'Status updated']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
