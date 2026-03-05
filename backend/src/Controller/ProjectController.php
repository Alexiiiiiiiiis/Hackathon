<?php
namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects', name: 'api_projects_')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository      $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $source = $data['gitUrl'] ?? $data['source'] ?? null;
        if (!$source || !is_string($source)) {
            return $this->json(['error' => 'gitUrl requis'], Response::HTTP_BAD_REQUEST);
        }

        $project = (new Project())
            ->setName($data['name'] ?? $this->guessProjectName($source))
            ->setSource($source)
            ->setSourceType($data['sourceType'] ?? $this->guessSourceType($source))
            ->setDetectedLanguage($data['language'] ?? null)
            ->setOwner($this->getUser());

        $this->em->persist($project);
        $this->em->flush();

        return $this->json([
            'id'        => $project->getId(),
            'gitUrl'    => $project->getSource(),
            'source'    => $project->getSource(),
            'status'    => 'pending',
            'createdAt' => $project->getCreatedAt()->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user     = $this->getUser();
        $projects = $this->repo->findAllWithLastScan();

        // Filtrer par propriétaire — compatibilité avec anciens projets sans owner
        $projects = array_filter($projects, function (Project $p) use ($user) {
            return $p->getOwner() === null || $p->getOwner() === $user;
        });

        return $this->json(array_values(array_map(fn(Project $p) => [
            'id'               => $p->getId(),
            'name'             => $p->getName(),
            'gitUrl'           => $p->getSource(),
            'source'           => $p->getSource(),
            'sourceType'       => $p->getSourceType(),
            'detectedLanguage' => $p->getDetectedLanguage(),
            'createdAt'        => $p->getCreatedAt()->format(\DATE_ATOM),
            'scansCount'       => $p->getScanResults()->count(),
        ], $projects)));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $p = $this->repo->find($id);
        if (!$p) return $this->json(['error' => 'Projet introuvable'], Response::HTTP_NOT_FOUND);

        // Vérifier que le projet appartient à l'utilisateur connecté
        if ($p->getOwner() !== null && $p->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id'               => $p->getId(),
            'name'             => $p->getName(),
            'gitUrl'           => $p->getSource(),
            'source'           => $p->getSource(),
            'sourceType'       => $p->getSourceType(),
            'localPath'        => $p->getLocalPath(),
            'detectedLanguage' => $p->getDetectedLanguage(),
            'createdAt'        => $p->getCreatedAt()->format(\DATE_ATOM),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $p = $this->repo->find($id);
        if (!$p) return $this->json(['error' => 'Projet introuvable'], Response::HTTP_NOT_FOUND);

        // Vérifier que le projet appartient à l'utilisateur connecté
        if ($p->getOwner() !== null && $p->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        if ($p->getLocalPath() && is_dir($p->getLocalPath())) {
            $this->removeDir($p->getLocalPath());
        }
        $this->em->remove($p);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function removeDir(string $path): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $f) {
            $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
        }
        rmdir($path);
    }

    private function guessSourceType(string $source): string
    {
        if (str_contains($source, 'github.com')) return 'github';
        if (str_contains($source, 'gitlab.com')) return 'gitlab';
        return 'git';
    }

    private function guessProjectName(string $source): string
    {
        $clean = preg_replace('#\.git$#', '', trim($source));
        $parts = preg_split('#/#', (string) $clean);
        $name  = end($parts);
        return $name ?: 'Projet sans nom';
    }
}