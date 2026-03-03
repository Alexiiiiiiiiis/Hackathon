<?php
namespace App\Service\Scanner;

use App\DTO\VulnerabilityDTO;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use Psr\Log\LoggerInterface;

class NpmAuditScanner implements ScannerInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function getName(): string { return 'npm_audit'; }

    public function isAvailable(): bool
    {
        exec('which npm 2>/dev/null', $o, $c);
        return $c === 0;
    }

    public function scan(string $projectPath): array
    {
        if (!file_exists("{$projectPath}/package.json")) return [];

        exec(
            sprintf('cd %s && npm audit --json 2>/dev/null', escapeshellarg($projectPath)),
            $out
        );

        $json = json_decode(implode("\n", $out), true);
        if (!$json || json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('npm audit JSON parse error');
            return [];
        }

        $results = [];
        foreach ($json['vulnerabilities'] ?? [] as $pkg => $data) {
            foreach ($data['via'] ?? [] as $via) {
                if (!is_array($via)) continue;
                $results[] = new VulnerabilityDTO(
                    toolSource:    $this->getName(),
                    ruleId:        (string) ($via['source'] ?? "npm-{$pkg}"),
                    message:       sprintf('[%s] %s — %s', $pkg, $via['title'] ?? 'Vulnerable dependency', $via['url'] ?? ''),
                    severity:      Severity::fromToolLevel($via['severity'] ?? 'info'),
                    filePath:      'package.json',
                    codeSnippet:   "Package: {$pkg}@" . ($data['range'] ?? '?'),
                    owaspCategory: OwaspCategory::A03_SUPPLY_CHAIN_FAILURES,
                );
            }
        }
        return $results;
    }
}