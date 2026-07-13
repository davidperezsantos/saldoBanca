<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/exchange-rates')]
class ExchangeRateController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'admin_exchange_rates_page')]
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

        $criteria = [];
        if ($providerId) {
            $criteria['provider'] = $providerId;
        }

        $rates = $this->entityManager->getRepository(ExchangeRate::class)->findBy(
            $criteria,
            ['fetchedAt' => 'DESC'],
            $request->query->getInt('limit', 100)
        );

        $data = array_map(function (ExchangeRate $rate) {
            return [
                'id' => $rate->getId(),
                'providerId' => $rate->getProvider()->getId(),
                'providerName' => $rate->getProvider()->getName(),
                'fromCurrency' => $rate->getFromCurrency(),
                'toCurrency' => $rate->getToCurrency(),
                'rate' => $rate->getRate(),
                'inverseRate' => $rate->getInverseRate(),
                'fetchedAt' => $rate->getFetchedAt()->format('Y-m-d H:i:s'),
                'isActive' => $rate->isActive(),
                'createdAt' => $rate->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $rates);

        return $this->success($data);
    }
}
