<?php

namespace App\Controller;

use App\Entity\ScanResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class ReportListController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/reports', name: 'reports_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 10);
        $perPage = max(1, min(50, $perPage));

        $name = trim((string) $request->query->get('name', ''));
        $repo = trim((string) $request->query->get('repo', ''));
        $type = $this->normalizeType((string) $request->query->get('type', ''));

        $dataQb = $this->em->createQueryBuilder()
            ->from(ScanResult::class, 's')
            ->join('s.project', 'p')
            ->leftJoin('s.vulnerabilities', 'v')
            ->select(
                's.id AS scanId',
                'p.id AS projectId',
                'p.name AS projectName',
                'p.source AS projectSource',
                's.globalScore AS globalScore',
                's.startedAt AS startedAt',
                'COUNT(v.id) AS vulnsCount'
            )
            ->where('s.status = :completed')
            ->setParameter('completed', 'completed')
            ->groupBy('s.id, p.id, p.name, p.source, s.globalScore, s.startedAt')
            ->orderBy('s.startedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $countQb = $this->em->createQueryBuilder()
            ->from(ScanResult::class, 's')
            ->join('s.project', 'p')
            ->select('COUNT(DISTINCT s.id)')
            ->where('s.status = :completed')
            ->setParameter('completed', 'completed');

        $this->applyTextFilters($dataQb, $name, $repo);
        $this->applyTextFilters($countQb, $name, $repo);
        $this->applyTypeFilter($dataQb, $type);
        $this->applyTypeFilter($countQb, $type);

        $rows = $dataQb->getQuery()->getArrayResult();
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = array_map(function (array $row): array {
            $score = (int) ($row['globalScore'] ?? 0);
            $vulnsCount = (int) ($row['vulnsCount'] ?? 0);
            $reportType = $this->computeTypeFromScore($score);
            $projectName = (string) ($row['projectName'] ?? '');
            $repository = $this->formatRepository(
                (string) ($row['projectSource'] ?? ''),
                $projectName
            );

            return [
                'scan_id' => (int) ($row['scanId'] ?? 0),
                'project_id' => (int) ($row['projectId'] ?? 0),
                'name' => $this->buildReportName($projectName, $repository),
                'repository' => $repository,
                'type' => $reportType,
                'date' => $this->formatDate($row['startedAt'] ?? null),
                'size_label' => $this->buildSizeLabel($vulnsCount, $reportType),
            ];
        }, $rows);

        return $this->json([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'items' => $items,
        ]);
    }

    private function applyTextFilters(QueryBuilder $qb, string $name, string $repo): void
    {
        if ($name !== '') {
            $qb->andWhere('p.name LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        if ($repo !== '') {
            $qb->andWhere('p.source LIKE :repo')
                ->setParameter('repo', '%' . $repo . '%');
        }
    }

    private function applyTypeFilter(QueryBuilder $qb, ?string $type): void
    {
        if ($type === null) {
            return;
        }

        if ($type === 'resume executif') {
            $qb->andWhere('s.globalScore >= :resumeMin')
                ->setParameter('resumeMin', 85);
            return;
        }

        if ($type === 'rapport complet') {
            $qb->andWhere('s.globalScore >= :fullMin')
                ->andWhere('s.globalScore < :fullMax')
                ->setParameter('fullMin', 60)
                ->setParameter('fullMax', 85);
            return;
        }

        $qb->andWhere('s.globalScore < :confMax OR s.globalScore IS NULL')
            ->setParameter('confMax', 60);
    }

    private function normalizeType(string $type): ?string
    {
        $type = strtolower(trim($type));
        if ($type === '') {
            return null;
        }

        $type = str_replace(
            ['Ã©', 'Ã¨', 'Ãª', 'Ã«', 'Ã ', 'Ã¢', 'Ã®', 'Ã¯', 'Ã´', 'Ã»', 'Ã¹', 'Ã§'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'],
            $type
        );
        $type = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'û', 'ù', 'ç'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'],
            $type
        );
        $type = preg_replace('/\s+/', ' ', $type) ?? $type;

        return match ($type) {
            'rapport complet', 'rapportcomplet', 'complet' => 'rapport complet',
            'conformite', 'conformite owasp' => 'conformite',
            'resume executif', 'resumeexecutif' => 'resume executif',
            default => null,
        };
    }

    private function computeTypeFromScore(int $score): string
    {
        if ($score >= 85) {
            return 'resume executif';
        }
        if ($score >= 60) {
            return 'rapport complet';
        }
        return 'conformite';
    }

    private function buildReportName(string $projectName, string $repository): string
    {
        $target = trim($projectName) !== '' ? $projectName : $repository;
        if ($target === '') {
            return 'Rapport de securite';
        }
        return 'Rapport de securite - ' . $target;
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

    private function buildSizeLabel(int $vulnsCount, string $type): string
    {
        $baseKb = match ($type) {
            'resume executif' => 700,
            'conformite' => 1100,
            default => 1500,
        };
        $totalKb = $baseKb + max(0, $vulnsCount) * 45;

        if ($totalKb >= 1024) {
            return number_format($totalKb / 1024, 1, '.', '') . ' MB';
        }
        return $totalKb . ' KB';
    }
}

