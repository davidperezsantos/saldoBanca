<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Descarga de los manuales en PDF desde el panel (íconos del header). Los archivos viven en
 * docs/, fuera de public/, para no exponer el resto de la carpeta (planes internos, etc.) como
 * contenido estático servible — solo estos dos, y solo a usuarios autenticados (misma regla que
 * el resto del panel, ver access_control en security.yaml).
 */
class ManualController extends BaseController
{
    private const MANUALS = [
        'usuario' => ['file' => 'manual-usuario.pdf', 'download' => 'SaldoGrin - Manual de Usuario.pdf'],
        'api' => ['file' => 'manual-api-integracion.pdf', 'download' => 'SaldoGrin - Manual de Integracion API.pdf'],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    #[Route('/manuales/{tipo}', name: 'manual_download', requirements: ['tipo' => 'usuario|api'], methods: ['GET'])]
    public function download(string $tipo): BinaryFileResponse
    {
        $manual = self::MANUALS[$tipo];

        $response = new BinaryFileResponse($this->projectDir . '/docs/' . $manual['file']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $manual['download']);

        // Sin esto, el default de BinaryFileResponse (Cache-Control: public + Last-Modified, sin
        // max-age) deja que el navegador sirva una copia vieja del PDF desde caché heurística sin
        // volver a pedirla — el manual se actualiza de tanto en tanto, así que cada clic debe traer
        // siempre el archivo actual.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }
}
