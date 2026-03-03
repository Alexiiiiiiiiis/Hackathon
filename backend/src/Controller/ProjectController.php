<?php

namespace App\Controller;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects')]
class ProjectController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->errorResponse('Payload JSON invalide', 400, 'invalid_json');
        }

        $gitUrl = $data['gitUrl'] ?? null;
        if (!is_string($gitUrl) || trim($gitUrl) === '') {
            return $this->errorResponse('gitUrl est obligatoire', 400, 'git_url_required');
        }

        $gitUrl = trim($gitUrl);
        if (!$this->isValidRepositoryUrl($gitUrl)) {
            return $this->errorResponse(
                'gitUrl doit etre une URL HTTPS valide de repository GitHub/GitLab',
                422,
                'invalid_git_url'
            );
        }

        $project = new Project();
        $project->setGitUrl($gitUrl);
        $project->setStatus(Project::STATUS_PENDING);

        $em->persist($project);
        $em->flush();

        return $this->json([
            'id'        => $project->getId(),
            'gitUrl'    => $project->getGitUrl(),
            'status'    => $project->getStatus(),
            'createdAt' => $project->getCreatedAt()->format('c'),
        ], 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): JsonResponse
    {
        $project = $em->getRepository(Project::class)->find($id);

        if (!$project) {
            return $this->errorResponse('Projet introuvable', 404, 'project_not_found');
        }

        return $this->json([
            'id'        => $project->getId(),
            'gitUrl'    => $project->getGitUrl(),
            'language'  => $project->getLanguage(),
            'status'    => $project->getStatus(),
            'createdAt' => $project->getCreatedAt()->format('c'),
        ]);
    }

    #[Route('', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $projects = $em->getRepository(Project::class)->findAll();

        $data = array_map(fn($p) => [
            'id'        => $p->getId(),
            'gitUrl'    => $p->getGitUrl(),
            'language'  => $p->getLanguage(),
            'status'    => $p->getStatus(),
            'createdAt' => $p->getCreatedAt()->format('c'),
        ], $projects);

        return $this->json($data);
    }

    private function errorResponse(string $message, int $status, string $code): JsonResponse
    {
        return $this->json(['error' => $message, 'code' => $code], $status);
    }

    private function isValidRepositoryUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        if (!in_array($host, ['github.com', 'gitlab.com'], true)) {
            return false;
        }

        $path = trim($parts['path'] ?? '', '/');
        if ($path === '') {
            return false;
        }

        $segments = explode('/', $path);

        return count($segments) >= 2 && $segments[0] !== '' && $segments[1] !== '';
    }
}
