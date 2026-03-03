<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Vulnerability;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\GitCloneService;

#[Route('/api/scan')]
class ScanController extends AbstractController
{
    #[Route('/{id}/launch', methods: ['POST'])]
    public function launch(int $id, EntityManagerInterface $em, GitCloneService $gitClone): JsonResponse
    {
        $project = $em->getRepository(Project::class)->find($id);

        if (!$project) {
            return $this->errorResponse('Projet introuvable', 404, 'project_not_found');
        }

        if ($project->getStatus() === Project::STATUS_CLONING || $project->getStatus() === Project::STATUS_SCANNING) {
            return $this->errorResponse('Une analyse est deja en cours pour ce projet', 409, 'scan_in_progress');
        }

        try {
            $project->setStatus(Project::STATUS_CLONING);
            $em->flush();

            $repoPath = $gitClone->clone($project->getGitUrl(), $project->getId());
            $language = $gitClone->detectLanguage($repoPath);

            $project->setLanguage($language);
            $project->setStatus(Project::STATUS_SCANNING);
            $em->flush();

            return $this->json([
                'message'   => 'Repository clone, analyse prete',
                'projectId' => $project->getId(),
                'language'  => $language,
                'status'    => $project->getStatus(),
            ]);

        } catch (\Exception $e) {
            $project->setStatus(Project::STATUS_ERROR);
            $em->flush();

            return $this->errorResponse('Echec du lancement de l analyse', 500, 'scan_launch_failed');
        }
    }

    #[Route('/{id}/results', methods: ['GET'])]
    public function results(int $id, EntityManagerInterface $em): JsonResponse
    {
        $project = $em->getRepository(Project::class)->find($id);

        if (!$project) {
            return $this->errorResponse('Projet introuvable', 404, 'project_not_found');
        }

        $vulns = $em->getRepository(Vulnerability::class)->findBy(['project' => $project]);

        $data = array_map(fn($v) => [
            'id'            => $v->getId(),
            'file'          => $v->getFile(),
            'line'          => $v->getLine(),
            'description'   => $v->getDescription(),
            'severity'      => $v->getSeverity(),
            'owaspCategory' => $v->getOwaspCategory(),
            'tool'          => $v->getTool(),
            'fixStatus'     => $v->getFixStatus(),
        ], $vulns);

        return $this->json($data);
    }

    #[Route('/{id}/owasp', methods: ['GET'])]
    public function owasp(int $id, EntityManagerInterface $em): JsonResponse
    {
        $project = $em->getRepository(Project::class)->find($id);

        if (!$project) {
            return $this->errorResponse('Projet introuvable', 404, 'project_not_found');
        }

        $vulns = $em->getRepository(Vulnerability::class)->findBy(['project' => $project]);

        $mapping = [];
        foreach ($vulns as $v) {
            $cat = $v->getOwaspCategory() ?? 'Inconnu';
            if (!isset($mapping[$cat])) {
                $mapping[$cat] = 0;
            }
            $mapping[$cat]++;
        }

        return $this->json($mapping);
    }

    private function errorResponse(string $message, int $status, string $code): JsonResponse
    {
        return $this->json(['error' => $message, 'code' => $code], $status);
    }
}
