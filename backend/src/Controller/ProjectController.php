<?php
namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects', name: 'api_projects_')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository      $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map(fn(Project $p) => [
            'id'               => $p->getId(),
            'name'             => $p->getName(),
            'source'           => $p->getSource(),
            'sourceType'       => $p->getSourceType(),
            'detectedLanguage' => $p->getDetectedLanguage(),
            'createdAt'        => $p->getCreatedAt()->format(\DATE_ATOM),
            'scansCount'       => $p->getScanResults()->count(),
        ], $this->repo->findAllWithLastScan()));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $p = $this->repo->find($id);
        if (!$p) return $this->json(['error' => 'Projet introuvable'], Response::HTTP_NOT_FOUND);
        return $this->json([
            'id'               => $p->getId(),
            'name'             => $p->getName(),
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
}