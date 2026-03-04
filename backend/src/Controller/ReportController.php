<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ScanResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/report')]
// Route rapport: generation HTML par projet
class ReportController extends AbstractController
{
    #[Route('/{id}', methods: ['GET'])]
    public function generate(int $id, EntityManagerInterface $em): Response
    {
        $project = $em->getRepository(Project::class)->find($id);
        if (!$project) {
            return new Response('<h1>Projet introuvable</h1>', 404, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        /** @var ScanResult[] $scans */
        $scans = $project->getScanResults()->toArray();
        usort($scans, static fn(ScanResult $a, ScanResult $b): int => $b->getStartedAt() <=> $a->getStartedAt());
        $latestScan = $scans[0] ?? null;
        $vulnerabilities = $latestScan ? $latestScan->getVulnerabilities()->toArray() : [];

        $bySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0, 'unknown' => 0];
        $byOwasp = [];

        foreach ($vulnerabilities as $vuln) {
            $severity = strtolower($vuln->getSeverity()->value);
            if (!isset($bySeverity[$severity])) {
                $severity = 'unknown';
            }
            $bySeverity[$severity]++;

            $owasp = $vuln->getOwaspCategory()->value ?: 'Inconnu';
            $byOwasp[$owasp] = ($byOwasp[$owasp] ?? 0) + 1;
        }

        ksort($byOwasp);

        $html = $this->renderReportHtml($project, $latestScan, $vulnerabilities, $bySeverity, $byOwasp);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<int, object> $vulnerabilities
     * @param array<string, int> $bySeverity
     * @param array<string, int> $byOwasp
     */
    private function renderReportHtml(
        Project $project,
        ?ScanResult $latestScan,
        array $vulnerabilities,
        array $bySeverity,
        array $byOwasp
    ): string {
        $rows = '';
        foreach ($vulnerabilities as $vuln) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s:%s</td><td>%s</td></tr>',
                htmlspecialchars((string) $vuln->getId(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $vuln->getToolSource(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $vuln->getSeverity()->value, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $vuln->getFilePath(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($vuln->getLine() ?? '-'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $vuln->getMessage(), ENT_QUOTES, 'UTF-8')
            );
        }

        $severityItems = '';
        $severityLabels = [
            'critical' => 'Critique',
            'high' => 'Haute',
            'medium' => 'Moyenne',
            'low' => 'Basse',
            'info' => 'Info',
            'unknown' => 'Inconnu',
        ];
        foreach ($bySeverity as $label => $count) {
            $severityItems .= sprintf(
                '<li><strong>%s</strong>: %d</li>',
                htmlspecialchars($severityLabels[$label] ?? $label, ENT_QUOTES, 'UTF-8'),
                $count
            );
        }

        $owaspItems = '';
        foreach ($byOwasp as $label => $count) {
            $owaspItems .= sprintf(
                '<li><strong>%s</strong>: %d</li>',
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                $count
            );
        }

        return sprintf(
            '<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rapport SecureScan - Projet %d</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; color: #1f2937; }
    h1, h2 { margin: 0 0 12px; }
    .meta { margin-bottom: 16px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
    .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
    table { width: 100%%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #f9fafb; }
  </style>
</head>
<body>
  <h1>Rapport de securite SecureScan</h1>
  <div class="meta">
    <div><strong>ID Projet :</strong> %d</div>
    <div><strong>ID Scan :</strong> %s</div>
    <div><strong>URL Git :</strong> %s</div>
    <div><strong>Langage :</strong> %s</div>
    <div><strong>Statut :</strong> %s</div>
    <div><strong>Genere le :</strong> %s</div>
    <div><strong>Total vulnerabilites :</strong> %d</div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Par severite</h2>
      <ul>%s</ul>
    </div>
    <div class="card">
      <h2>Par categorie OWASP</h2>
      <ul>%s</ul>
    </div>
  </div>

  <h2>Vulnerabilites detectees</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Outil</th>
        <th>Severite</th>
        <th>Emplacement</th>
        <th>Description</th>
      </tr>
    </thead>
    <tbody>%s</tbody>
  </table>
</body>
</html>',
            $project->getId(),
            $project->getId(),
            htmlspecialchars((string) ($latestScan?->getId() ?? '-'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $project->getSource(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) ($project->getDetectedLanguage() ?? 'inconnu'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) ($latestScan?->getStatus() ?? 'aucun scan'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((new \DateTimeImmutable())->format('c'), ENT_QUOTES, 'UTF-8'),
            count($vulnerabilities),
            $severityItems,
            $owaspItems ?: '<li><strong>Inconnu</strong>: 0</li>',
            $rows ?: '<tr><td colspan="5">Aucune vulnerabilite detectee</td></tr>'
        );
    }
}
