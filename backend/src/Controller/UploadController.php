<?php
namespace App\Controller;

use App\Entity\Project;
use App\Service\OWASPMappingService;
use App\Service\ScanOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/upload', name: 'api_upload_')]
class UploadController extends AbstractController
{
    public function __construct(
        private readonly ScanOrchestrator       $orchestrator,
        private readonly OWASPMappingService    $owaspMapper,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/zip', name: 'zip', methods: ['POST'])]
    public function uploadZip(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $name = $request->request->get('name', 'Projet uploadé');

        if (!$file) {
            return $this->json(['error' => 'Fichier ZIP requis (champ: file)'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getClientOriginalExtension() !== 'zip') {
            return $this->json(['error' => 'Seuls les fichiers .zip sont acceptés'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > 50 * 1024 * 1024) {
            return $this->json(['error' => 'Fichier trop volumineux (max 50MB)'], Response::HTTP_BAD_REQUEST);
        }

        // Extraction du ZIP
        $localPath = sys_get_temp_dir() . '/securescan_zip_' . uniqid();
        mkdir($localPath, 0777, true);

        $zip = new \ZipArchive();
        $zipPath = $file->getPathname();

        if ($zip->open($zipPath) !== true) {
            return $this->json(['error' => 'ZIP invalide ou corrompu'], Response::HTTP_BAD_REQUEST);
        }

        $zip->extractTo($localPath);
        $zip->close();

        // Si le ZIP contient un seul dossier racine, descend dedans
        $entries = array_diff(scandir($localPath), ['.', '..']);
        if (count($entries) === 1) {
            $candidate = $localPath . '/' . reset($entries);
            if (is_dir($candidate)) {
                $localPath = $candidate;
            }
        }

        // Détection du langage
        $language = $this->detectLanguage($localPath);

        $project = new Project();
        $project->setName($name);
        $project->setSource('zip_upload:' . $file->getClientOriginalName());
        $project->setSourceType('zip');
        $project->setLocalPath($localPath);
        $project->setDetectedLanguage($language);

        $this->em->persist($project);
        $this->em->flush();

        $scan = $this->orchestrator->runScan($project);

        return $this->json([
            'scanResultId' => $scan->getId(),
            'status'       => $scan->getStatus(),
            'language'     => $language,
            'stats'        => $this->owaspMapper->buildStats($scan),
        ], Response::HTTP_CREATED);
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