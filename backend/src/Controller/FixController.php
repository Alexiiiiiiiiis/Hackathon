<?php

namespace App\Controller;

use App\Entity\Fix;
use App\Entity\Vulnerability;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fix')]
class FixController extends AbstractController
{
    #[Route('/{vulnId}/apply', methods: ['POST'])]
    public function apply(int $vulnId, EntityManagerInterface $em): JsonResponse
    {
        $vuln = $em->getRepository(Vulnerability::class)->find($vulnId);

        if (!$vuln) {
            return $this->errorResponse('Vulnerabilite introuvable', 404, 'vulnerability_not_found');
        }

        $fix = $em->getRepository(Fix::class)->findOneBy(['vulnerability' => $vuln]);

        if (!$fix) {
            return $this->errorResponse('Aucun correctif disponible pour cette vulnerabilite', 404, 'fix_not_found');
        }

        if ($fix->getStatus() !== Fix::STATUS_PROPOSED) {
            return $this->errorResponse('Le correctif ne peut pas etre applique dans son etat actuel', 409, 'fix_invalid_state');
        }

        $fix->setStatus(Fix::STATUS_APPLIED);
        $vuln->setFixStatus(Vulnerability::FIX_STATUS_FIXED);
        $em->flush();

        return $this->json([
            'message'   => 'Correctif applique',
            'vulnId'    => $vulnId,
            'fixStatus' => $fix->getStatus(),
        ]);
    }

    #[Route('/{vulnId}/reject', methods: ['POST'])]
    public function reject(int $vulnId, EntityManagerInterface $em): JsonResponse
    {
        $vuln = $em->getRepository(Vulnerability::class)->find($vulnId);

        if (!$vuln) {
            return $this->errorResponse('Vulnerabilite introuvable', 404, 'vulnerability_not_found');
        }

        $fix = $em->getRepository(Fix::class)->findOneBy(['vulnerability' => $vuln]);

        if (!$fix) {
            return $this->errorResponse('Aucun correctif disponible', 404, 'fix_not_found');
        }

        if ($fix->getStatus() !== Fix::STATUS_PROPOSED) {
            return $this->errorResponse('Le correctif ne peut pas etre rejete dans son etat actuel', 409, 'fix_invalid_state');
        }

        $fix->setStatus(Fix::STATUS_REJECTED);
        $vuln->setFixStatus(Vulnerability::FIX_STATUS_REJECTED);
        $em->flush();

        return $this->json([
            'message'   => 'Correctif rejete',
            'vulnId'    => $vulnId,
            'fixStatus' => $fix->getStatus(),
        ]);
    }

    private function errorResponse(string $message, int $status, string $code): JsonResponse
    {
        return $this->json(['error' => $message, 'code' => $code], $status);
    }
}
