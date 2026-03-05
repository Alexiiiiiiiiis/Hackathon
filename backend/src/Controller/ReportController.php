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
    private const ALLOWED_SECTIONS = [
        'executive_summary',
        'technical_details',
        'owasp_mapping',
        'correction_plan',
        'code_examples',
        'compliance_checklist',
    ];

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
        $sections = $this->resolveSections($request);

        $baseName = sprintf('rapport-securite-projet-%d', $project->getId());
        $html = $this->renderReportHtml($project, $selectedScan, $vulnerabilities, $bySeverity, $byOwasp, $sections);

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
     * @param array<int, string> $sections
     */
    private function renderReportHtml(
        Project $project,
        ?ScanResult $latestScan,
        array $vulnerabilities,
        array $bySeverity,
        array $byOwasp,
        array $sections
    ): string {
        $hasExecutiveSummary = in_array('executive_summary', $sections, true);
        $hasTechnicalDetails = in_array('technical_details', $sections, true);
        $hasOwaspMapping = in_array('owasp_mapping', $sections, true);
        $hasCorrectionPlan = in_array('correction_plan', $sections, true);
        $hasCodeExamples = in_array('code_examples', $sections, true);
        $hasComplianceChecklist = in_array('compliance_checklist', $sections, true);

        $projectId = htmlspecialchars((string) $project->getId(), ENT_QUOTES, 'UTF-8');
        $scanId = htmlspecialchars((string) ($latestScan?->getId() ?? '-'), ENT_QUOTES, 'UTF-8');
        $source = htmlspecialchars((string) $project->getSource(), ENT_QUOTES, 'UTF-8');
        $language = htmlspecialchars((string) ($project->getDetectedLanguage() ?? 'inconnu'), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string) ($latestScan?->getStatus() ?? 'aucun scan'), ENT_QUOTES, 'UTF-8');
        $generatedAt = htmlspecialchars((new \DateTimeImmutable())->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
        $totalVulns = count($vulnerabilities);

        $rows = '';
        if ($hasTechnicalDetails) {
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

        $fixItems = [];
        $snippetBlocks = [];
        foreach ($vulnerabilities as $vuln) {
            $location = sprintf(
                '%s:%s',
                (string) ($vuln->getFilePath() ?? '-'),
                (string) ($vuln->getLine() ?? '-')
            );

            $suggestedFix = trim((string) ($vuln->getSuggestedFix() ?? ''));
            if ($suggestedFix !== '') {
                $fixItems[] = sprintf(
                    '<li><strong>%s</strong><br>%s</li>',
                    htmlspecialchars($location, ENT_QUOTES, 'UTF-8'),
                    nl2br(htmlspecialchars($suggestedFix, ENT_QUOTES, 'UTF-8'), false)
                );
            }

            $snippet = trim((string) ($vuln->getCodeSnippet() ?? ''));
            if ($snippet !== '') {
                $snippetBlocks[] = sprintf(
                    '<div class="snippet">
                        <div class="snippet-title">%s</div>
                        <pre>%s</pre>
                    </div>',
                    htmlspecialchars($location, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8')
                );
            }
        }

        $sectionsHtml = [];

        if ($hasExecutiveSummary) {
            $sectionsHtml[] = sprintf(
                '<div class="section-block">
                    <h2>Resume executif</h2>
                    <div class="meta">
                        <div class="meta-line"><strong>ID Projet :</strong> %s</div>
                        <div class="meta-line"><strong>ID Scan :</strong> %s</div>
                        <div class="meta-line"><strong>URL Git :</strong> %s</div>
                        <div class="meta-line"><strong>Langage :</strong> %s</div>
                        <div class="meta-line"><strong>Statut :</strong> %s</div>
                        <div class="meta-line"><strong>Genere le :</strong> %s</div>
                        <div class="meta-line"><strong>Total vulnerabilites :</strong> %d</div>
                    </div>
                </div>',
                $projectId,
                $scanId,
                $source,
                $language,
                $status,
                $generatedAt,
                $totalVulns
            );
        }

        if ($hasTechnicalDetails || $hasOwaspMapping) {
            $cards = '';
            if ($hasTechnicalDetails) {
                $cards .= sprintf(
                    '<div class="card">
                        <h2>Par severite</h2>
                        <ul>%s</ul>
                    </div>',
                    $severityItems
                );
            }
            if ($hasOwaspMapping) {
                $cards .= sprintf(
                    '<div class="card">
                        <h2>Par categorie OWASP</h2>
                        <ul>%s</ul>
                    </div>',
                    $owaspItems ?: '<li><strong>Inconnu</strong>: 0</li>'
                );
            }

            $sectionsHtml[] = '<div class="grid">' . $cards . '</div>';
        }

        if ($hasTechnicalDetails) {
            $sectionsHtml[] = sprintf(
                '<div class="section-block">
                    <h2>Details techniques des vulnerabilites</h2>
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
                </div>',
                $rows ?: '<tr><td colspan="5">Aucune vulnerabilite detectee</td></tr>'
            );
        }

        if ($hasCorrectionPlan) {
            $sectionsHtml[] = sprintf(
                '<div class="section-block">
                    <h2>Plan de correction</h2>
                    <ul>%s</ul>
                </div>',
                $fixItems !== [] ? implode('', $fixItems) : '<li>Aucun correctif propose pour le moment.</li>'
            );
        }

        if ($hasCodeExamples) {
            $sectionsHtml[] = sprintf(
                '<div class="section-block">
                    <h2>Exemples de code</h2>
                    %s
                </div>',
                $snippetBlocks !== [] ? implode('', $snippetBlocks) : '<div>Aucun extrait de code disponible.</div>'
            );
        }

        if ($hasComplianceChecklist) {
            $globalScore = (int) ($latestScan?->getGlobalScore() ?? 0);
            $criticalCount = (int) ($bySeverity['critical'] ?? 0);
            $checks = [
                ['Aucune vulnerabilite critique', $criticalCount === 0],
                ['Score global >= 80', $globalScore >= 80],
                ['Categorie OWASP renseignee', $byOwasp !== []],
                ['Correctifs proposes', $fixItems !== []],
            ];

            $checkItems = '';
            foreach ($checks as [$label, $ok]) {
                $checkItems .= sprintf(
                    '<li><span class="%s">%s</span> - %s</li>',
                    $ok ? 'status-ok' : 'status-ko',
                    $ok ? 'OK' : 'KO',
                    htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                );
            }

            $sectionsHtml[] = '<div class="section-block"><h2>Liste de conformite</h2><ul>' . $checkItems . '</ul></div>';
        }

        if ($sectionsHtml === []) {
            $sectionsHtml[] = '<div class="section-block">Aucune section selectionnee.</div>';
        }

        return sprintf(
            '<!doctype html>
            <html lang="fr">
            <head>
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <title>Rapport SecureScan - Projet %s</title>
              <style>
                body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 22px; color: #1f2937; font-size: 12px; line-height: 1.4; }
                h1, h2 { margin: 0 0 10px; }
                h1 { font-size: 22px; margin-bottom: 14px; }
                h2 { font-size: 15px; margin-top: 0; }
                .meta { margin-bottom: 6px; }
                .meta-line { margin: 2px 0; }
                .section-block { margin-bottom: 16px; }
                .grid { display: block; margin-bottom: 16px; }
                .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
                ul { margin: 6px 0 0 16px; padding: 0; }
                li { margin: 3px 0; }
                table { width: 100%%; border-collapse: collapse; table-layout: fixed; margin-top: 10px; }
                th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; vertical-align: top; word-break: break-word; overflow-wrap: anywhere; }
                th { background: #f3f6fb; font-size: 11px; }
                .col-id { width: 7%%; }
                .col-tool { width: 12%%; }
                .col-severity { width: 12%%; }
                .col-location { width: 27%%; }
                .col-message { width: 42%%; }
                .snippet { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 8px; overflow: hidden; }
                .snippet-title { background: #f8fafc; padding: 6px 8px; font-size: 11px; color: #334155; }
                pre { margin: 0; padding: 8px; background: #0f172a; color: #e2e8f0; font-size: 10px; white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere; }
                .status-ok { color: #15803d; font-weight: 700; }
                .status-ko { color: #b91c1c; font-weight: 700; }
              </style>
            </head>
            <body>
              <h1>Rapport de securite SecureScan</h1>
              %s
            </body>
            </html>',
            $projectId,
            implode('', $sectionsHtml)
        );
    }

    /**
     * @return array<int, string>
     */
    private function resolveSections(Request $request): array
    {
        $rawValues = [];
        $sectionsValue = $request->query->get('sections');

        if (is_array($sectionsValue)) {
            foreach ($sectionsValue as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $parts = preg_split('/[\s,;]+/', strtolower(trim((string) $value))) ?: [];
                $rawValues = array_merge($rawValues, $parts);
            }
        } elseif (is_scalar($sectionsValue)) {
            $single = strtolower(trim((string) $sectionsValue));
            if ($single !== '') {
                $rawValues = preg_split('/[\s,;]+/', $single) ?: [];
            }
        }

        $sections = [];
        foreach ($rawValues as $section) {
            $key = trim((string) $section);
            if ($key === '') {
                continue;
            }
            if (!in_array($key, self::ALLOWED_SECTIONS, true)) {
                continue;
            }
            if (in_array($key, $sections, true)) {
                continue;
            }
            $sections[] = $key;
        }

        return $sections === [] ? self::ALLOWED_SECTIONS : $sections;
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
