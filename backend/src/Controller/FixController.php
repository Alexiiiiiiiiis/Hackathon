<?php
namespace App\Controller;

use App\Repository\FixRepository;
use App\Repository\VulnerabilityRepository;
use App\Service\FixService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fix', name: 'api_fix_')]
class FixController extends AbstractController
{
    public function __construct(
        private readonly FixService              $fixService,
        private readonly VulnerabilityRepository $vulnRepo,
        private readonly FixRepository           $fixRepo,
    ) {}

    #[Route('/generate/{vulnId}', name: 'generate', methods: ['POST'])]
    public function generate(int $vulnId): JsonResponse
    {
        $vuln = $this->vulnRepo->find($vulnId);
        if (!$vuln) return $this->json(['error' => 'Vulnérabilité introuvable'], Response::HTTP_NOT_FOUND);
        $fix = $this->fixService->generateFix($vuln);
        return $this->json([
            'id'           => $fix->getId(),
            'originalCode' => $fix->getOriginalCode(),
            'fixedCode'    => $fix->getFixedCode(),
            'explanation'  => $fix->getExplanation(),
            'status'       => $fix->getStatus(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/apply', name: 'apply', methods: ['POST'])]
    public function apply(int $id): JsonResponse
    {
        $fix = $this->fixRepo->find($id);
        if (!$fix) {
            $vuln = $this->vulnRepo->find($id);
            if (!$vuln) {
                return $this->json(['error' => 'Fix ou vulnerabilite introuvable'], Response::HTTP_NOT_FOUND);
            }
            $fix = $this->fixService->generateFix($vuln);
        }
        try {
            $this->fixService->applyFix($fix);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return $this->json(['id' => $fix->getId(), 'status' => $fix->getStatus()]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(int $id): JsonResponse
    {
        $fix = $this->fixRepo->find($id);
        if (!$fix) {
            $vuln = $this->vulnRepo->find($id);
            if (!$vuln || !$vuln->getFix()) {
                return $this->json(['error' => 'Fix ou vulnerabilite introuvable'], Response::HTTP_NOT_FOUND);
            }
            $fix = $vuln->getFix();
        }
        $this->fixService->rejectFix($fix);
        return $this->json(['id' => $fix->getId(), 'status' => $fix->getStatus()]);
    }
}
