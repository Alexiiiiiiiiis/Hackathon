<?php
namespace App\Controller;

use App\Entity\Project;
use App\Entity\ScanResult;
use App\Repository\ProjectRepository;
use App\Repository\ScanResultRepository;
use App\Service\GitCloneService;
use App\Service\OWASPMappingService;
use App\Service\ScanOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/scan', name: 'api_scan_')]
// Routes scan: submit, launch, resultats, stats OWASP, latest
class ScanController extends AbstractController
{
    public function __construct(
        private readonly ScanOrchestrator       $orchestrator,
        private readonly GitCloneService        $gitClone,
        private readonly OWASPMappingService    $owaspMapper,
        private readonly EntityManagerInterface $em,
        private readonly ScanResultRepository   $scanResultRepo,
        private readonly ProjectRepository      $projectRepo,
    ) {}

    #[Route('/project', name: 'submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $gitUrl = $data['gitUrl'] ?? null;
        if (!$gitUrl) {
            return $this->json(['error' => 'gitUrl requis'], Response::HTTP_BAD_REQUEST);
        }

        $project = (new Project())
            ->setName($data['name'] ?? 'Projet sans nom')
            ->setSource($gitUrl)
            ->setSourceType($data['sourceType'] ?? $this->guessSourceType($gitUrl))
            ->setLocalPath(null)
            ->setDetectedLanguage(null)
            ->setOwner($this->getUser()); // ← FIX: associer l'utilisateur connecté

        $this->em->persist($project);
        $this->em->flush();

        try {
            $localPath = $this->gitClone->clone($gitUrl, (int) $project->getId());
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $project->setLocalPath($localPath);
        $project->setDetectedLanguage($this->gitClone->detectLanguage($localPath));

        $scan = $this->orchestrator->runScan($project);

        return $this->json([
            'scanResultId' => $scan->getId(),
            'status'       => $scan->getStatus(),
            'language'     => $project->getDetectedLanguage(),
            'stats'        => $this->owaspMapper->buildStats($scan),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'results', methods: ['GET'])]
    #[Route('/{id}/results', name: 'results_legacy', methods: ['GET'])]
    public function results(int $id): JsonResponse
    {
        $scan = $this->scanResultRepo->findWithVulnerabilities($id);
        if (!$scan) {
            return $this->json(['error' => 'Scan introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'         => $scan->getId(),
            'status'     => $scan->getStatus(),
            'startedAt'  => $scan->getStartedAt()->format(\DATE_ATOM),
            'finishedAt' => $scan->getFinishedAt()?->format(\DATE_ATOM),
            'stats'      => $this->owaspMapper->buildStats($scan),
            'vulns'      => array_map(fn($v) => [
                'id'            => $v->getId(),
                'toolSource'    => $v->getToolSource(),
                'ruleId'        => $v->getRuleId(),
                'message'       => $v->getMessage(),
                'severity'      => $v->getSeverity()->value,
                'filePath'      => $v->getFilePath(),
                'line'          => $v->getLine(),
                'codeSnippet'   => $v->getCodeSnippet(),
                'owaspCategory' => $v->getOwaspCategory()->value,
                'owaspLabel'    => $v->getOwaspCategory()->label(),
                'suggestedFix'  => $v->getSuggestedFix(),
                'fixStatus'     => $v->getFixStatus(),
            ], $scan->getVulnerabilities()->toArray()),
        ]);
    }

    #[Route('/{id}/owasp', name: 'owasp', methods: ['GET'])]
    public function owasp(int $id): JsonResponse
    {
        $scan = $this->scanResultRepo->findWithVulnerabilities($id);
        if (!$scan) {
            return $this->json(['error' => 'Scan introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->owaspMapper->buildStats($scan));
    }

    #[Route('/{id}/launch', name: 'launch', methods: ['POST'])]
    public function launch(int $id): JsonResponse
    {
        $project = $this->projectRepo->find($id);
        if (!$project) {
            return $this->json(['error' => 'Projet introuvable'], Response::HTTP_NOT_FOUND);
        }

        $path = $project->getLocalPath();
        if (!$path || !is_dir($path)) {
            try {
                $path = $this->gitClone->clone($project->getSource(), (int) $project->getId());
            } catch (\Throwable $e) {
                return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            $project->setLocalPath($path);
            $project->setDetectedLanguage($this->gitClone->detectLanguage($path));
            $this->em->flush();
        }

        $scan = $this->orchestrator->runScan($project);

        return $this->json([
            'message'      => 'Repo clone, scan pret',
            'projectId'    => $project->getId(),
            'scanResultId' => $scan->getId(),
            'language'     => $project->getDetectedLanguage(),
            'repoPath'     => $project->getLocalPath(),
            'status'       => $scan->getStatus(),
        ]);
    }

    #[Route('/project/{projectId}/latest', name: 'latest', methods: ['GET'])]
    public function latestByProject(int $projectId): JsonResponse
    {
        $project = $this->projectRepo->find($projectId);
        if (!$project) {
            return $this->json(['error' => 'Projet introuvable'], Response::HTTP_NOT_FOUND);
        }

        /** @var ScanResult[] $scans */
        $scans = $project->getScanResults()->toArray();
        usort($scans, static fn(ScanResult $a, ScanResult $b): int => $b->getStartedAt() <=> $a->getStartedAt());
        $latest = $scans[0] ?? null;

        if (!$latest) {
            return $this->json(['error' => 'Aucun scan pour ce projet'], Response::HTTP_NOT_FOUND);
        }

        return $this->results((int) $latest->getId());
    }

    private function guessSourceType(string $source): string
    {
        if (str_contains($source, 'github.com')) return 'github';
        if (str_contains($source, 'gitlab.com')) return 'gitlab';
        return 'git';
    }
}