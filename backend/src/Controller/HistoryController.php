<?php

namespace App\Controller;

use App\Entity\ScanResult;
use App\Entity\Vulnerability;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class HistoryController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 4);
        $perPage = max(1, min(50, $perPage));

        $search = trim((string) $request->query->get('search', ''));
        $status = trim((string) $request->query->get('status', ''));
        $dateFrom = $this->parseDate((string) $request->query->get('date_from', ''));

        $dataQb = $this->em->createQueryBuilder()
            ->from(ScanResult::class, 's')
            ->join('s.project', 'p')
            ->leftJoin('s.vulnerabilities', 'v')
            ->select(
                's.id AS scanId',
                'p.id AS projectId',
                'p.name AS projectName',
                'p.source AS projectSource',
                's.status AS scanStatus',
                's.globalScore AS globalScore',
                's.startedAt AS startedAt',
                'COUNT(v.id) AS vulnsCount'
            )
            ->groupBy('s.id, p.id, p.name, p.source, s.status, s.globalScore, s.startedAt')
            ->orderBy('s.startedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $countQb = $this->em->createQueryBuilder()
            ->from(ScanResult::class, 's')
            ->join('s.project', 'p')
            ->select('COUNT(DISTINCT s.id)');

        $this->applyHistoryFilters($dataQb, $search, $status, $dateFrom);
        $this->applyHistoryFilters($countQb, $search, $status, $dateFrom);

        $rows = $dataQb->getQuery()->getArrayResult();
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $analyses = array_map(function (array $row): array {
            $startedAt = $row['startedAt'] ?? null;
            $date = $this->formatDate($startedAt);

            return [
                'id' => (int) ($row['scanId'] ?? 0),
                'project_id' => (int) ($row['projectId'] ?? 0),
                'repository' => $this->formatRepository(
                    (string) ($row['projectSource'] ?? ''),
                    (string) ($row['projectName'] ?? '')
                ),
                'date' => $date,
                'score' => (int) ($row['globalScore'] ?? 0),
                'failles' => (int) ($row['vulnsCount'] ?? 0),
                'status' => $this->formatStatus((string) ($row['scanStatus'] ?? '')),
            ];
        }, $rows);

        return $this->json([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'analyses' => $analyses,
        ]);
    }

    #[Route('/history/stats', name: 'history_stats', methods: ['GET'])]
    public function historyStats(): JsonResponse
    {
        $totalAnalyses = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(ScanResult::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        $monthStart = new \DateTimeImmutable('first day of this month 00:00:00');
        $monthEnd = $monthStart->modify('+1 month');

        $thisMonth = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(ScanResult::class, 's')
            ->where('s.startedAt >= :monthStart')
            ->andWhere('s.startedAt < :monthEnd')
            ->setParameter('monthStart', $monthStart)
            ->setParameter('monthEnd', $monthEnd)
            ->getQuery()
            ->getSingleScalarResult();

        $avgRaw = $this->em->createQueryBuilder()
            ->select('AVG(s.globalScore)')
            ->from(ScanResult::class, 's')
            ->where('s.globalScore IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $averageScore = $avgRaw !== null ? (int) round((float) $avgRaw) : 0;

        $totalFailles = (int) $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Vulnerability::class, 'v')
            ->getQuery()
            ->getSingleScalarResult();

        $severityRows = $this->em->createQueryBuilder()
            ->select('v.severity AS severity, COUNT(v.id) AS cnt')
            ->from(Vulnerability::class, 'v')
            ->groupBy('v.severity')
            ->getQuery()
            ->getArrayResult();

        $bySeverity = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];
        foreach ($severityRows as $row) {
            $key = strtolower((string) ($row['severity'] ?? ''));
            if (array_key_exists($key, $bySeverity)) {
                $bySeverity[$key] = (int) ($row['cnt'] ?? 0);
            }
        }

        return $this->json([
            'total_analyses' => $totalAnalyses,
            'this_month' => $thisMonth,
            'average_score' => $averageScore,
            'total_failles' => $totalFailles,
            'critical' => $bySeverity['critical'],
            'high' => $bySeverity['high'],
            'medium' => $bySeverity['medium'],
            'low' => $bySeverity['low'],
            'info' => $bySeverity['info'],
        ]);
    }

    private function applyHistoryFilters(
        QueryBuilder $qb,
        string $search,
        string $status,
        ?\DateTimeImmutable $dateFrom
    ): void {
        if ($search !== '') {
            $qb->andWhere('p.name LIKE :search OR p.source LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($dateFrom !== null) {
            $dateTo = $dateFrom->modify('+1 day');
            $qb->andWhere('s.startedAt >= :dateFrom')
                ->andWhere('s.startedAt < :dateTo')
                ->setParameter('dateFrom', $dateFrom)
                ->setParameter('dateTo', $dateTo);
        }

        $normalized = $this->normalizeStatusToken($status);
        if ($normalized !== null) {
            $qb->andWhere('s.status = :status')->setParameter('status', $normalized);
        }
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($date === false) {
            return null;
        }

        return $date->setTime(0, 0, 0);
    }

    private function normalizeStatusToken(string $status): ?string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return null;
        }

        $status = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'û', 'ù', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'], $status);

        return match ($status) {
            'complete', 'completee', 'completed', 'done', 'termine', 'terminee' => 'completed',
            'en cours', 'encours', 'running' => 'running',
            'echoue', 'echec', 'failed' => 'failed',
            default => null,
        };
    }

    private function formatDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('d-m-Y');
        }

        if (is_string($date) && $date !== '') {
            try {
                return (new \DateTimeImmutable($date))->format('d-m-Y');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    private function formatRepository(string $source, string $fallback): string
    {
        if ($source !== '') {
            $path = parse_url($source, \PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $clean = trim($path, '/');
                return preg_replace('/\.git$/', '', $clean) ?: ($fallback ?: $source);
            }
        }

        return $fallback !== '' ? $fallback : $source;
    }

    private function formatStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed' => 'Complete',
            'running' => 'En cours',
            'failed' => 'Echoue',
            default => $status !== '' ? ucfirst($status) : 'Inconnu',
        };
    }
}
