<?php

namespace App\Controller;

use App\Entity\BodsJobStatusHist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

class JobHistoryController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/history', name: 'app_job_history')]
    public function index(Request $request): Response
    {
        // --- Get all filter parameters from the request ---
        $search = $request->query->get('search', '');
        $statusFilter = $request->query->get('status', '');
        $currentDateFilter = $request->query->get('currentDate', '');
        $startDateFilter = $request->query->get('startDate', '');
        $endDateFilter = $request->query->get('endDate', '');

        // --- Determine if any filters have been applied ---
        $hasFilters = !empty($search) || !empty($statusFilter) || !empty($currentDateFilter) || !empty($startDateFilter) || !empty($endDateFilter);
        $history = [];

        // --- Build and execute the query only if filters are present ---
        if ($hasFilters) {
            $queryBuilder = $this->entityManager->getRepository(BodsJobStatusHist::class)
                ->createQueryBuilder('h');

            // Text search for Job Name
            if (!empty($search)) {
                $queryBuilder->andWhere('h.jobName LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }

            // Filter by Status
            if (!empty($statusFilter)) {
                $queryBuilder->andWhere('h.status = :status')
                    ->setParameter('status', $statusFilter);
            }

            // Filter by Current Date
            if (!empty($currentDateFilter)) {
                $queryBuilder->andWhere('h.currentDate >= :currentDateStart')
                    ->andWhere('h.currentDate <= :currentDateEnd')
                    ->setParameter('currentDateStart', new DateTime($currentDateFilter . ' 00:00:00'))
                    ->setParameter('currentDateEnd', new DateTime($currentDateFilter . ' 23:59:59'));
            }

            // Filter by Start Date
            if (!empty($startDateFilter)) {
                $queryBuilder->andWhere('h.startDate >= :startDateStart')
                    ->andWhere('h.startDate <= :startDateEnd')
                    ->setParameter('startDateStart', new DateTime($startDateFilter . ' 00:00:00'))
                    ->setParameter('startDateEnd', new DateTime($startDateFilter . ' 23:59:59'));
            }

            // Filter by End Date
            if (!empty($endDateFilter)) {
                $queryBuilder->andWhere('h.endDate >= :endDateStart')
                    ->andWhere('h.endDate <= :endDateEnd')
                    ->setParameter('endDateStart', new DateTime($endDateFilter . ' 00:00:00'))
                    ->setParameter('endDateEnd', new DateTime($endDateFilter . ' 23:59:59'));
            }

            // --- Set default ordering ---
            $queryBuilder->orderBy('h.currentDate', 'DESC')
                ->addOrderBy('h.jobName', 'ASC');

            $history = $queryBuilder->getQuery()->getResult();
        }

        // --- Render the view with all necessary variables ---
        return $this->render('job_history/index.html.twig', [
            'history' => $history,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'currentDateFilter' => $currentDateFilter,
            'startDateFilter' => $startDateFilter,
            'endDateFilter' => $endDateFilter,
            'hasFilters' => $hasFilters,
            'statusOptions' => [
                'IP' => 'In Progress',
                'OK' => 'Completed',
                'ER' => 'Error',
                'PE' => 'Pending'
            ]
        ]);
    }
}
