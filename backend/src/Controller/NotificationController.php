<?php
namespace App\Controller;

use App\Repository\FixRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications', name: 'api_notifications_')]
class NotificationController extends AbstractController
{
    public function __construct(private readonly FixRepository $fixRepository) {}

    #[Route('/count', name: 'count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        return $this->json([
            'count' => $this->fixRepository->countPendingFixes(),
        ]);
    }
}

