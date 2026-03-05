<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ScanResult;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/report')]
class ReportController extends AbstractController
{
    #[Route('/{id}', methods: ['GET'])]
    public function generate(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $project = $em->getRepository(Project::class)->find($id);
        if (!$project) {
            return new Response('<h1>Projet introuvable</h1>', 404, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $scanId = (int) $request->query->get('scan_id', 0);
        $download = $request->query->getBoolean('download', false);
        $format = strtolower((string) $request->query->get('format', 'html'));
        $requestedFormat = in_array($format, ['html', 'pdf'], true) ? $format : 'html';

        $selectedScan = null;
        if ($scanId > 0) {
            $scan = $em->getRepository(ScanResult::class)->find($scanId);
            if ($scan instanceof ScanResult && $scan->getProject()?->getId() === $project->getId()) {
                $selectedScan = $scan;
            }
        }

        if (!$selectedScan) {
            /** @var ScanResult[] $scans */
            $scans = $project->getScanResults()->toArray();
            usort($scans, static fn(ScanResult $a, ScanResult $b): int => $b->getStartedAt() <=> $a->getStartedAt());
            $selectedScan = $scans[0] ?? null;
        }

        $vulnerabilities = $selectedScan ? $selectedScan->getVulnerabilities()->toArray() : [];
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

        $baseName = sprintf('rapport-securite-projet-%d', $project->getId());
        $html = $this->renderReportHtml($project, $selectedScan, $vulnerabilities, $bySeverity, $byOwasp);

        if ($requestedFormat === 'pdf') {
            $pdf = $this->renderPdfFromHtml($html);
            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    '%s; filename="%s.pdf"',
                    $download ? 'attachment' : 'inline',
                    $baseName
                ),
            ];

            return new Response($pdf, 200, $headers);
        }

        $headers = ['Content-Type' => 'text/html; charset=UTF-8'];
        if ($download) {
            $headers['Content-Disposition'] = sprintf('attachment; filename="%s.html"', $baseName);
        }

        return new Response($html, 200, $headers);
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
                '<tr>
                    <td class="col-id">%s</td>
                    <td class="col-tool">%s</td>
                    <td class="col-severity">%s</td>
                    <td class="col-location">%s:%s</td>
                    <td class="col-message">%s</td>
                </tr>',
                htmlspecialchars((string) $vuln->getId(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $vuln->getToolSource(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $vuln->getSeverity()->value, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($vuln->getFilePath() ?? '-'), ENT_QUOTES, 'UTF-8'),
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
                body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 22px; color: #1f2937; font-size: 12px; line-height: 1.4; }
                h1, h2 { margin: 0 0 10px; }
                h1 { font-size: 22px; }
                h2 { font-size: 15px; margin-top: 18px; }
                .meta { margin-bottom: 14px; }
                .meta-line { margin: 2px 0; }
                .grid { display: block; margin-bottom: 16px; }
                .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
                ul { margin: 6px 0 0 16px; padding: 0; }
                li { margin: 2px 0; }
                table { width: 100%%; border-collapse: collapse; table-layout: fixed; margin-top: 10px; }
                th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; vertical-align: top; word-break: break-word; overflow-wrap: anywhere; }
                th { background: #f3f6fb; font-size: 11px; }
                .col-id { width: 7%%; }
                .col-tool { width: 12%%; }
                .col-severity { width: 12%%; }
                .col-location { width: 27%%; }
                .col-message { width: 42%%; }
              </style>
            </head>
            <body>
              <h1>Rapport de securite SecureScan</h1>
              <div class="meta">
                <div class="meta-line"><strong>ID Projet :</strong> %d</div>
                <div class="meta-line"><strong>ID Scan :</strong> %s</div>
                <div class="meta-line"><strong>URL Git :</strong> %s</div>
                <div class="meta-line"><strong>Langage :</strong> %s</div>
                <div class="meta-line"><strong>Statut :</strong> %s</div>
                <div class="meta-line"><strong>Genere le :</strong> %s</div>
                <div class="meta-line"><strong>Total vulnerabilites :</strong> %d</div>
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
            htmlspecialchars((new \DateTimeImmutable())->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'),
            count($vulnerabilities),
            $severityItems,
            $owaspItems ?: '<li><strong>Inconnu</strong>: 0</li>',
            $rows ?: '<tr><td colspan="5">Aucune vulnerabilite detectee</td></tr>'
        );
    }

    private function renderPdfFromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
