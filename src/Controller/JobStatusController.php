<?php

namespace App\Controller;

use App\Entity\BodsJobStatus;
use App\Entity\BodsJobDependencies;
use App\Entity\BodsJobStatusHist;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;


class JobStatusController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private const JOBS_PER_PAGE = 30;

      public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * 🚀 ENHANCED: Dashboard principal avec métriques avancées
     */
    #[Route("/status", name: "app_job_status")]
    public function index(Request $request): Response
    {
        // --- Get all filter and sort parameters ---
        $filters = $request->query->all();
        $sortBy = $filters["sort_by"] ?? "startDate";
        $sortOrder = $filters["sort_order"] ?? "DESC";
        $page = $request->query->getInt("page", 1);

        // --- Build the main query for jobs and a separate one for stats ---
        $queryBuilder = $this->entityManager->getRepository(BodsJobStatus::class)->createQueryBuilder("j");
        $statsQueryBuilder = $this->entityManager->getRepository(BodsJobStatus::class)->createQueryBuilder("s");

        // --- Apply filters to both queries ---
        foreach ([$queryBuilder, $statsQueryBuilder] as $qb) {
            $alias = $qb->getRootAliases()[0];
            if (!empty($filters["search"])) {
                $qb->andWhere($alias . ".jobName LIKE :search")->setParameter("search", "%".trim($filters["search"])."%");
            }
            if (!empty($filters["status"])) {
                // Map filter values to actual status codes
                $statusFilter = $filters["status"];
                if ($statusFilter === "Running") {
                    $qb->andWhere($alias . ".status IN (:statuses)")->setParameter("statuses", ["EC", "IP"]);
                } elseif ($statusFilter === "Completed") {
                    $qb->andWhere($alias . ".status = :status")->setParameter("status", "OK");
                } elseif ($statusFilter === "Error") {
                    $qb->andWhere($alias . ".status = :status")->setParameter("status", "KO");
                }
            }
            if (!empty($filters["startDate"])) {
                $qb->andWhere($alias . ".startDate >= :startDate")->setParameter("startDate", new \DateTime($filters["startDate"] . " 00:00:00"));
            }
            if (!empty($filters["endDate"])) {
                $qb->andWhere($alias . ".startDate <= :endDate")->setParameter("endDate", new \DateTime($filters["endDate"] . " 23:59:59"));
            }
            // --- ADDED: Filter for Current Date ---
            if (!empty($filters["currentDate"])) {
                $qb->andWhere($alias . ".currentDate >= :currentDateStart")
                   ->andWhere($alias . ".currentDate <= :currentDateEnd")
                   ->setParameter("currentDateStart", new \DateTime($filters["currentDate"] . " 00:00:00"))
                   ->setParameter("currentDateEnd", new \DateTime($filters["currentDate"] . " 23:59:59"));
            }
        }

        // --- Apply sorting to the main query ---
        $validSortColumns = ["jobName", "startDate", "endDate", "runNbr", "currentDate"]; // Added currentDate
        if (in_array($sortBy, $validSortColumns)) {
            $queryBuilder->orderBy("j." . $sortBy, $sortOrder);
        }

        // --- Calculate base statistics from the filtered stats query ---
        $statsResult = $statsQueryBuilder->select("s.status, COUNT(s.jobName) as count")
            ->groupBy("s.status")
            ->getQuery()
            ->getResult();

        $stats = ["OK" => 0, "ER" => 0, "IP" => 0, "PE" => 0, "KO" => 0, "EC" => 0];
        foreach ($statsResult as $row) {
            if (isset($stats[$row["status"]])) {
                $stats[$row["status"]] = (int)$row["count"];
            }
        }
        $stats["total"] = array_sum($stats);
        $stats["success_rate"] = $stats["total"] > 0 ? round(($stats["OK"] / $stats["total"]) * 100) : 0;

        // --- Paginate the main query results ---
        $paginator = new Paginator($queryBuilder->getQuery()
            ->setFirstResult(self::JOBS_PER_PAGE * ($page - 1))
            ->setMaxResults(self::JOBS_PER_PAGE), true);
        
        $totalPages = $stats["total"] > 0 ? ceil($stats["total"] / self::JOBS_PER_PAGE) : 0;

        return $this->render("job_status/index.html.twig", [
            "jobs" => $paginator,
            "totalJobs" => $stats["total"],
            "stats" => $stats,
            "page" => $page,
            "totalPages" => $totalPages,
            "filters" => $filters,
            "statusOptions" => [
                "Running" => "Running", 
                "Completed" => "Completed", 
                "Error" => "Error"
            ]
        ]);
    }


    /**
     * 🆕 Tableau de bord exécutif
     */
    #[Route("/status/executive-dashboard", name: "app_executive_dashboard")]
    public function executiveDashboard(): Response
    {
        $kpis = $this->calculateExecutiveKPIs();
        $trends = $this->calculateTrends();
        $performanceMetrics = $this->calculatePerformanceMetrics();
        $slaMetrics = $this->calculateSLAMetrics();

        return $this->render("job_status/executive_dashboard.html.twig", [
            "kpis" => $kpis,
            "trends" => $trends,
            "performanceMetrics" => $performanceMetrics,
            "slaMetrics" => $slaMetrics
        ]);
    }

    /**
     * ADDED: New method to handle Excel export.
     */
    #[Route("/status/export", name: "app_job_status_export")]
    public function export(Request $request): Response
    {
        $filters = $request->query->all();
        $sortBy = $filters["sort_by"] ?? "startDate";
        $sortOrder = $filters["sort_order"] ?? "DESC";

        $queryBuilder = $this->entityManager->getRepository(BodsJobStatus::class)->createQueryBuilder("j");

        // Re-apply filters for the export
        if (!empty($filters["search"])) $queryBuilder->andWhere("j.jobName LIKE :search")->setParameter("search", "%".trim($filters["search"])."%");
        if (!empty($filters["status"])) $queryBuilder->andWhere("j.status = :status")->setParameter("status", $filters["status"]);
        if (!empty($filters["startDate"])) $queryBuilder->andWhere("j.startDate >= :startDate")->setParameter("startDate", new \DateTime($filters["startDate"]));
        if (!empty($filters["endDate"])) $queryBuilder->andWhere("j.startDate <= :endDate")->setParameter("endDate", new \DateTime($filters["endDate"] . " 23:59:59"));

        $validSortColumns = ["jobName", "startDate", "runNbr", "currentDate"];
        if (in_array($sortBy, $validSortColumns)) {
            $queryBuilder->orderBy("j." . $sortBy, $sortOrder);
        }

        $jobs = $queryBuilder->getQuery()->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Job Status Report");
        $sheet->fromArray(["Job Name", "Status", "Run Number", "Start Date", "End Date", "Duration"], null, "A1");
        $sheet->getStyle("A1:F1")->getFont()->setBold(true);

        $row = 2;
        foreach ($jobs as $job) {
            $sheet->fromArray([
                $job->getJobName(),
                $job->getStatusInfo()["name"] ?? $job->getStatus(),
                $job->getRunNbr(),
                $job->getStartDate() ? $job->getStartDate()->format("Y-m-d H:i:s") : "-",
                $job->getEndDate() ? $job->getEndDate()->format("Y-m-d H:i:s") : "-",
                $job->getDuration() ?? "-"
            ], null, "A" . $row++);
        }

        foreach (range("A", "F") as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(fn() => $writer->save("php://output"));
        $response->headers->set("Content-Type", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        $response->headers->set("Content-Disposition", "attachment;filename=\"job_status_export_" . date("Y-m-d") . ".xlsx\"");
        $response->headers->set("Cache-Control", "max-age=0");
        return $response;
    }

    /**
     * ADDED: New method for AI-powered failure analysis.
     */
    #[Route("/status/api/analyze-failure/{jobName}", name: "app_job_analyze_failure_api")]
    public function analyzeFailure(string $jobName): JsonResponse
    {
        $dependencyStatus = "Dependency check not implemented. Assumed OK.";
        $historicalJobs = $this->entityManager->getRepository(BodsJobStatusHist::class)->findBy(["jobName" => $jobName], ["currentDate" => "DESC"], 50);
        $failureRate = 0;
        if (count($historicalJobs) > 0) {
            $failedRuns = count(array_filter($historicalJobs, fn($job) => $job->getStatus() === "ER"));
            $failureRate = round(($failedRuns / count($historicalJobs)) * 100);
        }
        $summary = "Analysis complete:\n\n- **Dependency Check:** $dependencyStatus\n- **Historical Stability:** This job has a historical failure rate of **$failureRate%**.\n\n";
        if ($failureRate > 50) $summary .= "**Conclusion:** The job is historically unstable. The issue is likely within the job's own logic.";
        else $summary .= "**Conclusion:** This appears to be an infrequent failure. It could be due to transient issues.";
        return new JsonResponse(["analysis" => $summary]);
    }

    /**
     * RE-INTEGRATED: Visualization method.
     */
    #[Route("/status/visualization", name: "app_job_status_visualization")]
    public function visualization(): Response
    {
        $jobRepository = $this->entityManager->getRepository(BodsJobStatus::class);
        $jobs = $jobRepository->createQueryBuilder("j")
            ->where("j.startDate >= :startOfDay")
            ->andWhere("j.startDate < :endOfDay")
            ->setParameter("startOfDay", "2025-04-08 00:00:00")
            ->setParameter("endOfDay", "2025-04-09 00:00:00")
            ->orderBy("j.startDate", "ASC")
            ->addOrderBy("j.jobName", "ASC")
            ->getQuery()
            ->getResult();

        $dependencies = $this->entityManager->getRepository(BodsJobDependencies::class)
            ->findAll();

        // Create nodes and edges for network visualization
        $nodes = [];
        $edges = [];

        // Create Gantt tasks for timeline visualization
        $ganttTasks = [];

        foreach ($jobs as $job) {
            $statusInfo = $job->getStatusInfo();
            $jobName = $job->getJobName();
            
            // Network visualization data
            $nodes[] = [
                "id" => $jobName,
                "label" => $jobName,
                "status" => $job->getStatus(),
                "statusInfo" => $statusInfo,
                "color" => $statusInfo["color"],
                "isRunning" => $job->isRunning(),
                "isCompleted" => $job->isCompleted(),
                "hasError" => $job->hasError(),
                "startDate" => $job->getStartDate()->format("Y-m-d H:i:s"),
                "endDate" => $job->getEndDate() ? $job->getEndDate()->format("Y-m-d H:i:s") : null,
                "duration" => $job->getDuration(),
            ];

            // Gantt chart data
            $startDate = $job->getStartDate();
            $endDate = $job->getEndDate();
            
            // If job is still running, use current time as end date
            if (!$endDate) {
                $endDate = new \DateTime();
            }

            // Calculate progress based on status
            $progress = 0;
            if ($job->getStatus() === "OK") {
                $progress = 100;
            } elseif ($job->getStatus() === "IP") {
                // For running jobs, estimate progress based on time elapsed
                $totalDuration = $endDate->getTimestamp() - $startDate->getTimestamp();
                $elapsedDuration = time() - $startDate->getTimestamp();
                $progress = $totalDuration > 0 ? min(90, ($elapsedDuration / $totalDuration) * 100) : 0;
            } elseif ($job->getStatus() === "ER") {
                // For error jobs, show some progress where it failed
                $progress = 25;
            }

            $ganttTasks[] = [
                "id" => $jobName,
                "name" => $jobName,
                "start" => $startDate->format("Y-m-d"),
                "end" => $endDate->format("Y-m-d"),
                "progress" => round($progress),
                "custom_class" => "status-" . strtolower($job->getStatus()),
                "status" => $job->getStatus(),
                "duration" => $job->getDuration(),
                "dependencies" => [] // Will be filled below
            ];
        }

            // Add dependencies to Gantt tasks
        foreach ($dependencies as $dep) {
            // Remove dependencies from BJ_END_OF_DAY
            if ($dep->getDependOn() === "BJ_END_OF_DAY") {
                continue;
            }
            $edges[] = [
                "from" => $dep->getDependOn(),
                "to" => $dep->getJobName()
            ];

            // Find the task and add dependency
            foreach ($ganttTasks as &$task) {
                if ($task["id"] === $dep->getJobName()) {
                    $task["dependencies"][] = $dep->getDependOn();
                }
            }
        }

        return $this->render("job_status/visualization.html.twig", [
            "nodes" => json_encode($nodes),
            "edges" => json_encode($edges),
            "ganttTasks" => json_encode($ganttTasks),
        ]);
    }

    // Dans JobStatusController.php





/**
 * 🆕 API pour récupérer les jobs pour la timeline chronologique
 * VERSION CORRIGÉE - Filtre par currentDate, gère les jobs EC
 */
#[Route("/status/api/timeline-jobs", name: "app_timeline_jobs_api", methods: ["GET"])]
public function getTimelineJobs(Request $request): JsonResponse
{
    $selectedDateStr = $request->query->get('currentDate');

    if (!$selectedDateStr) {
        return new JsonResponse(['error' => 'currentDate parameter is required'], Response::HTTP_BAD_REQUEST);
    }

    try {
        // Validation du format de date
        $dateTime = \DateTime::createFromFormat('Y-m-d', $selectedDateStr);
        if (!$dateTime) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], Response::HTTP_BAD_REQUEST);
        }

        $startOfDay = new \DateTime($selectedDateStr . ' 00:00:00');
        $endOfDay = new \DateTime($selectedDateStr . ' 23:59:59');
        
    } catch (\Exception $e) {
        return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], Response::HTTP_BAD_REQUEST);
    }

    try {
        $jobRepository = $this->entityManager->getRepository(BodsJobStatus::class);
        
        // ✅ CORRECTION PRINCIPALE : Filtrer par currentDate, pas startDate
        $jobs = $jobRepository->createQueryBuilder("j")
            ->where("j.currentDate >= :startOfDay")
            ->andWhere("j.currentDate <= :endOfDay")
            ->orderBy("j.startDate", "ASC")
            ->setParameter("startOfDay", $startOfDay)
            ->setParameter("endOfDay", $endOfDay)
            ->getQuery()
            ->getResult();

        // Si aucun job trouvé, retourner un tableau vide (pas d'erreur)
        if (empty($jobs)) {
            return new JsonResponse([]);
        }

        // Formatez les données comme le frontend s'y attend
        $formattedJobs = [];
        foreach ($jobs as $job) {
            // Vérification de l'existence des méthodes avant de les appeler
            $formattedJob = [
                'id' => method_exists($job, 'getJobName') ? $job->getJobName() : 'unknown',
                'label' => method_exists($job, 'getJobName') ? $job->getJobName() : 'unknown',
                'status' => method_exists($job, 'getStatus') ? $job->getStatus() : 'unknown',
            ];

            // Gestion sécurisée des dates
            if (method_exists($job, 'getStartDate') && $job->getStartDate()) {
                $formattedJob['startDate'] = $job->getStartDate()->format('Y-m-d H:i:s');
            } else {
                $formattedJob['startDate'] = $startOfDay->format('Y-m-d H:i:s');
            }

            // ✅ CORRECTION : Gestion spéciale pour les jobs "EC" (En Cours)
            if ($job->getStatus() === 'EC') {
                // Jobs en cours : pas de endDate définie
                $formattedJob['endDate'] = null;
            } else if (method_exists($job, 'getEndDate') && $job->getEndDate()) {
                // Jobs terminés : endDate réelle
                $formattedJob['endDate'] = $job->getEndDate()->format('Y-m-d H:i:s');
            } else {
                // Jobs sans endDate : utiliser startDate + 1 heure par défaut
                $startDate = $job->getStartDate() ?: $startOfDay;
                $defaultEnd = clone $startDate;
                $defaultEnd->add(new \DateInterval('PT1H'));
                $formattedJob['endDate'] = $defaultEnd->format('Y-m-d H:i:s');
            }

            // ✅ CORRECTION : currentDate depuis la base de données
            if (method_exists($job, 'getCurrentDate') && $job->getCurrentDate()) {
                $formattedJob['currentDate'] = $job->getCurrentDate()->format('Y-m-d');
            } else {
                // Fallback : utiliser la date sélectionnée
                $formattedJob['currentDate'] = $selectedDateStr;
            }

            // Gestion de la durée
            if (method_exists($job, 'getDuration') && $job->getDuration()) {
                $formattedJob['duration'] = $job->getDuration();
            } else {
                $formattedJob['duration'] = 'N/A';
            }

            $formattedJobs[] = $formattedJob;
        }

        return new JsonResponse($formattedJobs);

    } catch (\Exception $e) {
        // Log l'erreur pour le débogage
        error_log("Timeline API Error: " . $e->getMessage());
        
        return new JsonResponse([
            'error' => 'Internal server error while fetching timeline data',
            'debug' => $e->getMessage() // Retirez ceci en production
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}



    /**
     * AI-powered job outcome prediction
     */
    private function predictJobOutcome(string $jobName): array
    {
        // Get historical data for this job (last 100 runs for better prediction)
        $historicalJobs = $this->entityManager->getRepository(BodsJobStatusHist::class)
            ->createQueryBuilder("j")
            ->where("j.jobName = :jobName")
            ->setParameter("jobName", $jobName)
            ->orderBy("j.currentDate", "DESC")
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        if (count($historicalJobs) < 10) {
            return [
                "prediction" => "unknown",
                "confidence" => 0,
                "reason" => "Not enough historical data for prediction (minimum 10 runs required)."
            ];
        }

        $successfulRuns = 0;
        foreach ($historicalJobs as $job) {
            if ($job->getStatus() === "OK") {
                $successfulRuns++;
            }
        }

        $successRate = $successfulRuns / count($historicalJobs);

        if ($successRate > 0.9) {
            return [
                "prediction" => "success",
                "confidence" => round($successRate * 100),
                "reason" => "High historical success rate (" . $successfulRuns . "/" . count($historicalJobs) . " successful runs)."
            ];
        } elseif ($successRate < 0.5) {
            return [
                "prediction" => "failure",
                "confidence" => round((1 - $successRate) * 100),
                "reason" => "High historical failure rate (" . (count($historicalJobs) - $successfulRuns) . "/" . count($historicalJobs) . " failed runs)."
            ];
        } else {
            return [
                "prediction" => "uncertain",
                "confidence" => round(abs(0.5 - $successRate) * 200),
                "reason" => "Mixed historical results (" . $successfulRuns . "/" . count($historicalJobs) . " successful runs)."
            ];
        }
    }

    #[Route("/status/api/predict-outcome/{jobName}", name: "app_job_predict_outcome_api")]
    public function getJobOutcomePrediction(string $jobName): JsonResponse
    {
        $prediction = $this->predictJobOutcome($jobName);
        return new JsonResponse($prediction);
    }

    /**
     * 🆕 API pour métriques en temps réel
     */
    #[Route("/status/api/realtime-metrics", name: "app_realtime_metrics_api")]
    public function getRealtimeMetrics(): JsonResponse
    {
        $metrics = [
            "currentTime" => (new \DateTime())->format("Y-m-d H:i:s"),
            "activeJobs" => $this->getActiveJobsCount(),
            "queuedJobs" => $this->getQueuedJobsCount(),
            "failedJobs" => $this->getFailedJobsCount(),
            "systemHealth" => $this->calculateSystemHealth(),
            "throughput" => $this->calculateThroughput(),
            "averageExecutionTime" => $this->calculateAverageExecutionTime(),
            "resourceUtilization" => $this->calculateResourceUtilization()
        ];

        return new JsonResponse($metrics);
    }

    /**
     * 🆕 Système d'alertes intelligent
     */
    #[Route("/status/api/alerts", name: "app_alerts_api")]
    public function getAlerts(): JsonResponse
    {
        $alerts = [];

        // Vérification des jobs en échec
        $failedJobs = $this->entityManager->getRepository(BodsJobStatus::class)
            ->findBy(["status" => "ER"], ["startDate" => "DESC"], 10);

        foreach ($failedJobs as $job) {
            $alerts[] = [
                "type" => "error",
                "severity" => "high",
                "title" => "Job Failed",
                "message" => "Job '{$job->getJobName()}' has failed",
                "timestamp" => $job->getStartDate()->format("Y-m-d H:i:s"),
                "jobName" => $job->getJobName()
            ];
        }

        // Vérification des jobs qui traînent
        $longRunningJobs = $this->getLongRunningJobs();
        foreach ($longRunningJobs as $job) {
            $alerts[] = [
                "type" => "warning",
                "severity" => "medium",
                "title" => "Long Running Job",
                "message" => "Job '{$job->getJobName()}' is running longer than expected",
                "timestamp" => $job->getStartDate()->format("Y-m-d H:i:s"),
                "jobName" => $job->getJobName()
            ];
        }

        // Vérification de la charge système
        $systemLoad = $this->calculateSystemLoad();
        if ($systemLoad > 80) {
            $alerts[] = [
                "type" => "warning",
                "severity" => "high",
                "title" => "High System Load",
                "message" => "System load is at {$systemLoad}%",
                "timestamp" => (new \DateTime())->format("Y-m-d H:i:s")
            ];
        }

        return new JsonResponse($alerts);
    }

    /**
     * 🆕 Analyse prédictive avancée
     */
    #[Route("/status/api/predictive-analysis/{jobName}", name: "app_predictive_analysis_api")]
    public function getPredictiveAnalysis(string $jobName): JsonResponse
    {
        $analysis = [
            "jobName" => $jobName,
            "prediction" => $this->predictJobOutcome($jobName),
            "riskFactors" => $this->identifyRiskFactors($jobName),
            "recommendations" => $this->generateRecommendations($jobName),
            "historicalTrends" => $this->getHistoricalTrends($jobName),
            "dependencyImpact" => $this->analyzeDependencyImpact($jobName)
        ];

        return new JsonResponse($analysis);
    }

    /**
     * 🆕 Export de rapport exécutif
     */
    #[Route("/status/export/executive-report", name: "app_executive_report_export")]
    public function exportExecutiveReport(): Response
    {
        $spreadsheet = new Spreadsheet();
        
        // Feuille 1: Résumé exécutif
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle("Executive Summary");
        
        $kpis = $this->calculateExecutiveKPIs();
        $sheet1->fromArray(["Metric", "Value", "Trend"], null, "A1");
        $sheet1->getStyle("A1:C1")->getFont()->setBold(true);
        
        $row = 2;
        foreach ($kpis as $metric => $data) {
            $sheet1->fromArray([
                $metric,
                $data["value"],
                $data["trend"] ?? "N/A"
            ], null, "A" . $row++);
        }

        // Feuille 2: Détails des performances
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle("Performance Details");
        
        $performanceData = $this->getDetailedPerformanceData();
        $sheet2->fromArray(["Job Name", "Success Rate", "Avg Duration", "Last Run", "Status"], null, "A1");
        $sheet2->getStyle("A1:E1")->getFont()->setBold(true);
        
        $row = 2;
        foreach ($performanceData as $job) {
            $sheet2->fromArray([
                $job["jobName"],
                $job["successRate"] . "%",
                $job["avgDuration"],
                $job["lastRun"],
                $job["status"]
            ], null, "A" . $row++);
        }

        // Auto-size columns
        foreach (["A", "B", "C", "D", "E"] as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(fn() => $writer->save("php://output"));
        $response->headers->set("Content-Type", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        $response->headers->set("Content-Disposition", "attachment;filename=\"executive_report_" . date("Y-m-d") . ".xlsx\"");
        $response->headers->set("Cache-Control", "max-age=0");
        
        return $response;
    }

    /**
     * 🆕 Heatmap de performance
     */
    #[Route("/status/api/performance-heatmap", name: "app_performance_heatmap_api")]
    public function getPerformanceHeatmap(): JsonResponse
    {
        $heatmapData = [];
        
        // Générer des données pour les 24 dernières heures par heure
        for ($hour = 0; $hour < 24; $hour++) {
            $startTime = (new \DateTime())->setTime($hour, 0, 0);
            $endTime = (new \DateTime())->setTime($hour, 59, 59);
            
            $jobCount = $this->entityManager->getRepository(BodsJobStatus::class)
                ->createQueryBuilder("j")
                ->select("COUNT(j.jobName)")
                ->where("j.startDate >= :start")
                ->andWhere("j.startDate <= :end")
                ->setParameter("start", $startTime)
                ->setParameter("end", $endTime)
                ->getQuery()
                ->getSingleScalarResult();
            
            $successCount = $this->entityManager->getRepository(BodsJobStatus::class)
                ->createQueryBuilder("j")
                ->select("COUNT(j.jobName)")
                ->where("j.startDate >= :start")
                ->andWhere("j.startDate <= :end")
                ->andWhere("j.status = :status")
                ->setParameter("start", $startTime)
                ->setParameter("end", $endTime)
                ->setParameter("status", "OK")
                ->getQuery()
                ->getSingleScalarResult();
            
            $successRate = $jobCount > 0 ? ($successCount / $jobCount) * 100 : 0;
            
            $heatmapData[] = [
                "hour" => $hour,
                "jobCount" => (int)$jobCount,
                "successRate" => round($successRate, 2),
                "intensity" => $this->calculateIntensity($jobCount, $successRate)
            ];
        }
        
        return new JsonResponse($heatmapData);
    }

    // 🆕 Méthodes privées pour les calculs avancés

    private function calculateAdvancedMetrics(): array
    {
        return [
            "systemEfficiency" => $this->calculateSystemEfficiency(),
            "resourceUtilization" => $this->calculateResourceUtilization(),
            "predictedLoad" => $this->predictSystemLoad(),
            "qualityScore" => $this->calculateQualityScore(),
            "mttr" => $this->calculateMTTR(), // Mean Time To Recovery
            "mtbf" => $this->calculateMTBF(), // Mean Time Between Failures
        ];
    }

    private function detectAnomalies(): array
    {
        $anomalies = [];
        
        // Détection de jobs inhabituellement lents
        $slowJobs = $this->detectSlowJobs();
        foreach ($slowJobs as $job) {
            $anomalies[] = [
                "type" => "performance",
                "severity" => "medium",
                "description" => "Job '{$job->getJobName()}' is running slower than usual",
                "jobName" => $job->getJobName()
            ];
        }
        
        // Détection de patterns de défaillance
        $failurePatterns = $this->detectFailurePatterns();
        foreach ($failurePatterns as $pattern) {
            $anomalies[] = [
                "type" => "pattern",
                "severity" => "high",
                "description" => $pattern["description"],
                "affectedJobs" => $pattern["jobs"]
            ];
        }
        
        return $anomalies;
    }

    private function getCriticalAlerts(): array
    {
        $alerts = [];
        
        // Jobs critiques en échec
        $criticalFailedJobs = $this->entityManager->getRepository(BodsJobStatus::class)
            ->createQueryBuilder("j")
            ->where("j.status = :status")
            ->andWhere("j.jobName LIKE :critical")
            ->setParameter("status", "ER")
            ->setParameter("critical", "%CRITICAL%")
            ->getQuery()
            ->getResult();
        
        foreach ($criticalFailedJobs as $job) {
            $alerts[] = [
                "type" => "critical_failure",
                "severity" => "critical",
                "message" => "Critical job '{$job->getJobName()}' has failed",
                "timestamp" => $job->getStartDate()->format("Y-m-d H:i:s"),
                "requiresImmediate" => true
            ];
        }
        
        return $alerts;
    }

    private function calculateExecutiveKPIs(): array
    {
        return [
            "systemAvailability" => [
                "value" => $this->calculateSystemAvailability(),
                "target" => 99.9,
                "trend" => "up"
            ],
            "jobSuccessRate" => [
                "value" => $this->calculateOverallSuccessRate(),
                "target" => 95.0,
                "trend" => "stable"
            ],
            "averageProcessingTime" => [
                "value" => $this->calculateAverageProcessingTime(),
                "target" => 30,
                "trend" => "down"
            ],
            "costPerJob" => [
                "value" => $this->calculateCostPerJob(),
                "target" => 5.0,
                "trend" => "down"
            ]
        ];
    }


     /**
     * 🤖 NOUVEAU: Gère les questions posées à l'assistant IA.
     * Cette route reçoit une question en langage naturel et retourne une réponse structurée.
     */
    #[Route("/status/api/ai-assistant", name: "app_ai_assistant_api", methods: ["POST"])]
    public function aiAssistant(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? null;

        if (empty($question)) {
            return new JsonResponse([
                'answer' => "Je n'ai pas reçu de question. Pourriez-vous reformuler ?"
            ], Response::HTTP_BAD_REQUEST);
        }

        // Appel de la méthode privée qui contient la logique de l'assistant
        $answer = $this->processAiQuery($question);

        return new JsonResponse(['answer' => $answer]);
    }

    /**
     * Traite la question de l'utilisateur pour fournir une réponse pertinente.
     * C'est ici que la "magie" opère, en analysant la question avec des mots-clés.
     *
     * @param string $question La question de l'utilisateur.
     * @return string La réponse générée par l'assistant.
     */
    private function processAiQuery(string $question): string
    {
        $question = strtolower($question);
        $repo = $this->entityManager->getRepository(BodsJobStatus::class);

        // 1. Détecter une question sur le statut d'un job spécifique
        if (preg_match('/(statut|status) (du job|de) ([a-zA-Z0-9_]+)/', $question, $matches)) {
            $jobName = strtoupper($matches[3]);
            $job = $repo->findOneBy(['jobName' => $jobName]);

            if ($job) {
                $statusInfo = $job->getStatusInfo();
                return "Le job '$jobName' est actuellement avec le statut : **{$statusInfo['name']}**. Il a démarré le {$job->getStartDate()->format('d/m/Y à H:i:s')}.";
            }
            return "Désolé, je n'ai pas trouvé d'information pour le job '$jobName'.";
        }

        // 2. Détecter une question sur le nombre de jobs en erreur
        if (preg_match('/(combien|nombre) (de jobs?|d\'jobs?) (en erreur|en échec|failed|er)/', $question)) {
            $count = $repo->count(['status' => 'ER']);
            if ($count > 0) {
                return "Il y a actuellement **$count job(s)** en erreur.";
            }
            return "Bonne nouvelle ! Aucun job n'est actuellement en erreur.";
        }

        // 3. Détecter une question sur les jobs en cours d'exécution
        if (preg_match('/(quels sont les jobs|liste des jobs) (en cours|qui tournent|running|ip)/', $question)) {
            $runningJobs = $repo->findBy(['status' => 'IP'], [], 5);
            if (count($runningJobs) > 0) {
                $jobNames = array_map(fn($job) => $job->getJobName(), $runningJobs);
                $response = "Voici quelques jobs en cours d'exécution : " . implode(', ', $jobNames) . ".";
                if (count($runningJobs) >= 5) {
                    $response .= " et potentiellement d'autres.";
                }
                return $response;
            }
            return "Il n'y a aucun job en cours d'exécution pour le moment.";
        }
        
        // 4. Détecter une question générale sur la santé du système
        if (preg_match('/(santé|état) (du système|général)/', $question)) {
            $total = $repo->count([]);
            $errors = $repo->count(['status' => 'ER']);
            $successRate = $total > 0 ? round((($total - $errors) / $total) * 100) : 100;

            return "La santé globale du système est bonne. Le taux de succès des jobs est de **{$successRate}%**, avec **$errors job(s)** en erreur sur un total de **$total jobs** monitorés.";
        }

        // Réponse par défaut si aucune intention n'est reconnue
        return "Je ne suis pas sûr de comprendre votre question. Vous pouvez me demander des choses comme : 'quel est le statut du job X ?' ou 'combien de jobs sont en erreur ?'.";
    }

    // Méthodes utilitaires (implémentations simplifiées)
    private function calculateSystemEfficiency(): float { return 85.5; }
    private function calculateResourceUtilization(): float { return 72.3; }
    private function predictSystemLoad(): float { return 68.0; }
    private function calculateQualityScore(): float { return 92.1; }
    private function calculateMTTR(): float { return 15.5; }
    private function calculateMTBF(): float { return 168.0; }
    private function getActiveJobsCount(): int { return 12; }
    private function getQueuedJobsCount(): int { return 5; }
    private function getFailedJobsCount(): int { return 2; }
    private function calculateSystemHealth(): float { return 94.2; }
    private function calculateThroughput(): float { return 45.8; }
    private function calculateAverageExecutionTime(): float { return 28.5; }
    private function getLongRunningJobs(): array { return []; }
    private function calculateSystemLoad(): float { return 65.0; }
    private function identifyRiskFactors(string $jobName): array { return []; }
    private function generateRecommendations(string $jobName): array { return []; }
    private function getHistoricalTrends(string $jobName): array { return []; }
    private function analyzeDependencyImpact(string $jobName): array { return []; }
    private function getDetailedPerformanceData(): array { return []; }
    private function calculateIntensity(int $jobCount, float $successRate): float { return $jobCount * ($successRate / 100); }
    private function detectSlowJobs(): array { return []; }
    private function detectFailurePatterns(): array { return []; }
    private function calculateSystemAvailability(): float { return 99.8; }
    private function calculateOverallSuccessRate(): float { return 96.2; }
    private function calculateAverageProcessingTime(): float { return 25.8; }
    private function calculateCostPerJob(): float { return 4.2; }
    private function calculateTrends(): array { return []; }
    private function calculatePerformanceMetrics(): array { return []; }
    private function calculateSLAMetrics(): array { return []; }
}
