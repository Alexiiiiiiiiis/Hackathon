<?php
namespace App\Controller;

use App\Entity\Project;
use App\Repository\ScanResultRepository;
use App\Service\OWASPMappingService;
use App\Service\ScanOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/scan', name: 'api_scan_')]
class ScanController extends AbstractController
{
    public function __construct(
        private readonly ScanOrchestrator       $orchestrator,
        private readonly OWASPMappingService    $owaspMapper,
        private readonly EntityManagerInterface $em,
        private readonly ScanResultRepository   $scanResultRepo,
    ) {}

    #[Route('/project', name: 'submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $gitUrl = $data['gitUrl'] ?? null;
        if (!$gitUrl) return $this->json(['error' => 'gitUrl requis'], Response::HTTP_BAD_REQUEST);

        $localPath = sys_get_temp_dir() . '/securescan_' . uniqid();
        $process   = new Process(['git', 'clone', '--depth', '1', $gitUrl, $localPath]);
        $process->setTimeout(120)->run();

        if (!$process->isSuccessful()) {
            return $this->json(
                ['error' => 'Clonage Git échoué', 'detail' => $process->getErrorOutput()],
                Response::HTTP_BAD_REQUEST
            );
        }

        $project = (new Project())
            ->setName($data['name'] ?? 'Projet sans nom')
            ->setSource($gitUrl)
            ->setSourceType($data['sourceType'] ?? 'github')
            ->setLocalPath($localPath)
            ->setDetectedLanguage($this->detectLanguage($localPath));

        $this->em->persist($project);
        $this->em->flush();

        $scan = $this->orchestrator->runScan($project);

        return $this->json([
            'scanResultId' => $scan->getId(),
            'status'       => $scan->getStatus(),
            'language'     => $project->getDetectedLanguage(),
            'stats'        => $this->owaspMapper->buildStats($scan),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'results', methods: ['GET'])]
    public function results(int $id): JsonResponse
    {
        $scan = $this->scanResultRepo->findWithVulnerabilities($id);
        if (!$scan) return $this->json(['error' => 'Scan introuvable'], Response::HTTP_NOT_FOUND);

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

    private function detectLanguage(string $path): string
    {
        $map = [
            'javascript' => ['package.json'],
            'php'        => ['composer.json', 'index.php'],
            'python'     => ['requirements.txt', 'pyproject.toml'],
            'java'       => ['pom.xml', 'build.gradle'],
            'ruby'       => ['Gemfile'],
        ];
        foreach ($map as $lang => $files) {
            foreach ($files as $f) {
                if (file_exists("{$path}/{$f}")) return $lang;
            }
        }
        return 'unknown';
    }
}