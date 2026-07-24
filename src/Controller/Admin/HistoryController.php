<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\BalanceMovement;
use App\Entity\Balance\OperationEvent;
use App\Services\Balance\OperationEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/history')]
class HistoryController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OperationEventService $operationEventService,
    ) {
    }

    #[Route('', name: 'admin_history_page', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('history:view');

        return $this->render('admin/history.html.twig');
    }

    #[Route('/list', name: 'admin_history_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('history:view');

        $filters = [
            'limit' => $request->query->getInt('limit', 50),
            'offset' => $request->query->getInt('offset', 0),
        ];

        if ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }

        if ($request->query->has('movementType')) {
            $filters['movementType'] = $request->query->get('movementType');
        }

        if ($request->query->has('dateFrom')) {
            $filters['dateFrom'] = $request->query->get('dateFrom');
        }

        if ($request->query->has('dateTo')) {
            $filters['dateTo'] = $request->query->get('dateTo');
        }

        $qb = $this->entityManager->getRepository(BalanceMovement::class)->createQueryBuilder('bm');

        if (isset($filters['accountId'])) {
            $qb->andWhere('bm.account = :accountId')
               ->setParameter('accountId', $filters['accountId']);
        }

        if (isset($filters['movementType'])) {
            $qb->andWhere('bm.movementType = :movementType')
               ->setParameter('movementType', $filters['movementType']);
        }

        if (isset($filters['dateFrom'])) {
            $qb->andWhere('bm.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom'] . ' 00:00:00');
        }

        if (isset($filters['dateTo'])) {
            $qb->andWhere('bm.createdAt <= :dateTo')
               ->setParameter('dateTo', $filters['dateTo'] . ' 23:59:59');
        }

        $qb->orderBy('bm.createdAt', 'DESC')
           ->setMaxResults($filters['limit'])
           ->setFirstResult($filters['offset']);

        $movements = $qb->getQuery()->getResult();

        $data = array_map(function (BalanceMovement $m) {
            return [
                'id' => $m->getId(),
                'accountId' => $m->getAccount()->getId(),
                'accountNumber' => $m->getAccount()->getAccountNumber(),
                'movementType' => $m->getMovementType(),
                'amount' => $m->getAmount(),
                'balanceBefore' => $m->getBalanceBefore(),
                'balanceAfter' => $m->getBalanceAfter(),
                'referenceType' => $m->getReferenceType(),
                'referenceId' => $m->getReferenceId(),
                'description' => $m->getDescription(),
                'currency' => $m->getCurrency(),
                'performedBy' => $m->getPerformedBy(),
                'createdAt' => $m->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $movements);

        return $this->success($data);
    }

    /**
     * Línea de tiempo de estados de un registro puntual (factura/recarga/transferencia/consumo de
     * autorizado), para el diálogo que se abre al hacer clic en una fila del Historial. Si el
     * registro solo tuvo un paso, devuelve un solo elemento.
     */
    #[Route('/timeline', name: 'admin_history_timeline', methods: ['GET'])]
    public function timeline(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('history:view');

        $entityType = $request->query->get('entityType');
        $entityId = $request->query->get('entityId');

        if (!$entityType || !$entityId) {
            return $this->error('entityType y entityId son requeridos');
        }

        $events = $this->operationEventService->getTimeline($entityType, $entityId);

        $data = array_map(fn(OperationEvent $e) => [
            'id' => $e->getId(),
            'status' => $e->getStatus(),
            'performedBy' => $e->getPerformedBy(),
            'notes' => $e->getNotes(),
            'createdAt' => $e->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $events);

        return $this->success($data);
    }
}
