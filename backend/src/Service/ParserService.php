<?php
namespace App\Service;

use App\DTO\VulnerabilityDTO;
use App\Enum\OwaspCategory;
use App\Enum\Severity;

class ParserService
{
    /** @return VulnerabilityDTO[] */
    public function parseSemgrep(array $payload): array
    {
        $results = [];
        foreach ($payload['results'] ?? [] as $r) {
            $results[] = new VulnerabilityDTO(
                toolSource: 'semgrep',
                ruleId: (string) ($r['check_id'] ?? 'semgrep-rule'),
                message: (string) ($r['extra']['message'] ?? 'Semgrep finding'),
                severity: Severity::fromToolLevel((string) ($r['extra']['severity'] ?? 'info')),
                filePath: $r['path'] ?? null,
                line: isset($r['start']['line']) ? (int) $r['start']['line'] : null,
                codeSnippet: $r['extra']['lines'] ?? null,
                owaspCategory: OwaspCategory::UNKNOWN
            );
        }

        return $results;
    }

    /** @return VulnerabilityDTO[] */
    public function parseNpmAudit(array $payload): array
    {
        $results = [];
        foreach ($payload['vulnerabilities'] ?? [] as $pkg => $data) {
            foreach ($data['via'] ?? [] as $via) {
                if (!is_array($via)) {
                    continue;
                }

                $results[] = new VulnerabilityDTO(
                    toolSource: 'npm_audit',
                    ruleId: (string) ($via['source'] ?? "npm-{$pkg}"),
                    message: (string) ($via['title'] ?? "Vulnerable package: {$pkg}"),
                    severity: Severity::fromToolLevel((string) ($via['severity'] ?? 'info')),
                    filePath: 'package.json',
                    line: null,
                    codeSnippet: "Package: {$pkg}@" . ($data['range'] ?? '?'),
                    owaspCategory: OwaspCategory::A03_SUPPLY_CHAIN_FAILURES
                );
            }
        }

        return $results;
    }

    /** @return VulnerabilityDTO[] */
    public function parseTrufflehogLine(string $jsonLine): array
    {
        $decoded = json_decode($jsonLine, true);
        if (!is_array($decoded)) {
            return [];
        }

        return [new VulnerabilityDTO(
            toolSource: 'trufflehog',
            ruleId: (string) ($decoded['DetectorName'] ?? 'secret'),
            message: (string) ($decoded['Raw'] ?? 'Secret detecte'),
            severity: Severity::HIGH,
            filePath: $decoded['SourceMetadata']['Data']['Filesystem']['file'] ?? null,
            line: isset($decoded['SourceMetadata']['Data']['Filesystem']['line']) ? (int) $decoded['SourceMetadata']['Data']['Filesystem']['line'] : null,
            codeSnippet: null,
            owaspCategory: OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES
        )];
    }
}
