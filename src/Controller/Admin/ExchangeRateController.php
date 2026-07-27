<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRate;
use App\Repository\Balance\CurrencyRepository;
use App\Repository\Balance\ExchangeRateSnapshotRepository;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Util\BcMath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/exchange-rates')]
class ExchangeRateController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private APIExchangeRate $apiExchangeRate,
        private ExchangeRateSnapshotRepository $snapshotRepository,
        private CurrencyRepository $currencyRepository,
    ) {
    }

    #[Route('', name: 'admin_exchange_rates_page', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('exchange.rates:view');

        return $this->render('admin/exchange_rates.html.twig');
    }

    #[Route('/list', name: 'admin_exchange_rates_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('exchange.rates:view');

        $providerId = $request->query->get('providerId');
        $activeCodes = $this->currencyRepository->findActiveCodes();

        if (empty($activeCodes)) {
            return $this->success([]);
        }

        // Solo se muestran tasas de monedas activas en el nomenclador (App\Entity\Balance\Currency).
        // El filtro por toCurrency debe ir en la query, antes del limit: si se aplicara después
        // (sobre el top N por fetchedAt), las monedas activas podían no estar entre esas N filas
        // (sin un criterio de desempate para fetchedAt) y la lista salía vacía aunque sí hubiera
        // tasas vigentes para ellas.
        $criteria = ['toCurrency' => $activeCodes];
        if ($providerId) {
            $criteria['provider'] = $providerId;
        }

        $rates = $this->entityManager->getRepository(ExchangeRate::class)->findBy(
            $criteria,
            ['isActive' => 'DESC', 'fetchedAt' => 'DESC'],
            $request->query->getInt('limit', 100)
        );

        if (empty($rates)) {
            return $this->success($this->buildFromSnapshot($activeCodes));
        }

        $data = array_map(function (ExchangeRate $rate) {
            return [
                'id' => $rate->getId(),
                'providerId' => $rate->getProvider()?->getId(),
                'providerName' => $rate->getProvider()?->getName() ?? 'Manual',
                'fromCurrency' => $rate->getFromCurrency(),
                'toCurrency' => $rate->getToCurrency(),
                'rate' => $rate->getRate(),
                'inverseRate' => $rate->getInverseRate(),
                'fetchedAt' => $rate->getFetchedAt()->format('Y-m-d H:i:s'),
                'isActive' => $rate->isActive(),
                'isLocked' => $rate->isLocked(),
                'createdAt' => $rate->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $rates);

        return $this->success($data);
    }

    #[Route('/fetch', name: 'admin_exchange_rates_fetch', methods: ['POST'])]
    public function fetch(): JsonResponse
    {
        $this->denyAccessUnlessGranted('exchange.rates:fetch');

        try {
            $this->validateCsrfToken();
            $result = $this->apiExchangeRate->refreshRates();
            if ($result['success']) {
                return $this->success(null, 'Tasas actualizadas correctamente');
            }
            return $this->error($result['error'] ?? 'Error al obtener tasas');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Route('/manual', name: 'admin_exchange_rate_manual_create', methods: ['POST'])]
    public function createManual(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('exchange.rates:fetch');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $toCurrency = strtoupper((string) ($data['toCurrency'] ?? ''));
            $rate = (string) ($data['rate'] ?? '');
            $locked = (bool) ($data['locked'] ?? false);

            if (!$toCurrency || !$rate) {
                return $this->error('toCurrency y rate son requeridos');
            }

            $this->apiExchangeRate->createManualRate($toCurrency, $rate, $locked);

            return $this->success(null, 'Tasa manual registrada', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function buildFromSnapshot(array $activeCodes): array
    {
        $snapshot = $this->snapshotRepository->findLast();
        if (!$snapshot) return [];

        $base = $snapshot->getBase();
        $allRates = $snapshot->getRates();
        $data = [];

        foreach ($allRates as $currency => $rate) {
            if ($currency === $base) continue;
            if (!in_array($currency, $activeCodes, true)) continue;
            $data[] = [
                'id' => $snapshot->getId(),
                'providerId' => null,
                'providerName' => $base . ' API',
                'fromCurrency' => $base,
                'toCurrency' => $currency,
                'rate' => (string)$rate,
                'inverseRate' => $rate > 0 ? BcMath::round(bcdiv('1', (string)$rate, 12), 8) : null,
                'fetchedAt' => $snapshot->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isActive' => true,
                'isLocked' => false,
                'createdAt' => $snapshot->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }
}
