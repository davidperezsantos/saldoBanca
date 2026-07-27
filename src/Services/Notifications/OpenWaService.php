<?php

namespace App\Services\Notifications;

use Twig\Environment;

class OpenWaService
{
    private $apikey;
    private $baseUrl;
    private $sessionId;

    public function __construct(string $apikey, string $baseUrl, string $sessionId)
    {
        $this->apikey = $apikey;
        $this->baseUrl = $baseUrl;
        $this->sessionId = $sessionId;
    }

    private function request(string $method, string $endpoint, array $data = array())
    {
        $url = sprintf(
            '%s/%s',
            rtrim($this->baseUrl, '/'),
            ltrim($endpoint, '/')
        );

        $contextOptions = array(
            'http' => array(
                'method' => $method,
            'header' => implode("\r\n", array(
                'Authorization: Bearer ' . $this->apikey,
                'Content-Type: application/json',
                'Accept: application/json',
            )),
                'content' => $data ? json_encode($data) : '',
                'ignore_errors' => true,
                'timeout' => 10,
            ),
        );

        $context = stream_context_create($contextOptions);
        $responseBody = @file_get_contents($url, false, $context);

        if ($responseBody === false) {
            throw new \RuntimeException('No se pudo realizar la solicitud a OpenWA.');
        }

        // Check HTTP status code from response headers
        if (isset($http_response_header)) {
            $statusCode = 0;
            if (preg_match('#HTTP/\d+(?:\.\d+)?\s+(\d+)#', $http_response_header[0], $m)) {
                $statusCode = (int)$m[1];
            }
            if ($statusCode >= 400) {
                throw new \RuntimeException("OpenWA respondió con código {$statusCode}: {$responseBody}");
            }
        }

        $decoded = json_decode($responseBody, true);

        if (!is_array($decoded)) {
            return array('raw' => $responseBody);
        }

        return $decoded;
    }

    /**
     * Enviar mensaje
     */
    public function sendMessage(string $phone, string $message)
    {
        if (empty($this->sessionId)) {
            throw new \RuntimeException('OpenWA session ID no configurado.');
        }
        if (empty($phone) || empty($message)) {
            throw new \RuntimeException('Teléfono o mensaje vacío.');
        }

        return $this->request(
            'POST',
            sprintf(
                'sessions/%s/messages/send-text',
                $this->sessionId
            ),
            array(
                'chatId' => str_replace('+', '', $phone) . '@c.us',
                'text' => $message,
            )
        );
    }
}
