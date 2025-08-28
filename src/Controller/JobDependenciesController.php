<?php

namespace App\Controller;

use App\Entity\BodsJobDependencies;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JobDependenciesController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/dependencies', name: 'app_job_dependencies')]
    public function index(Request $request): Response
    {
        // --- Get separate search terms from the request ---
        $jobNameSearch = $request->query->get('jobNameSearch', '');
        $dependOnSearch = $request->query->get('dependOnSearch', '');

        $queryBuilder = $this->entityManager->getRepository(BodsJobDependencies::class)
            ->createQueryBuilder('d');

        // --- Apply filter for "Job Name" if provided ---
        if (!empty($jobNameSearch)) {
            $queryBuilder->andWhere('d.jobName LIKE :jobNameSearch')
                ->setParameter('jobNameSearch', '%' . $jobNameSearch . '%');
        }

        // --- Apply filter for "Depends On" if provided ---
        if (!empty($dependOnSearch)) {
            $queryBuilder->andWhere('d.dependOn LIKE :dependOnSearch')
                ->setParameter('dependOnSearch', '%' . $dependOnSearch . '%');
        }

        $queryBuilder->orderBy('d.jobName', 'ASC')
            ->addOrderBy('d.dependOn', 'ASC');

        $dependencies = $queryBuilder->getQuery()->getResult();

        return $this->render('job_dependencies/index.html.twig', [
            'dependencies' => $dependencies,
            'jobNameSearch' => $jobNameSearch,
            'dependOnSearch' => $dependOnSearch,
        ]);
    }
}
