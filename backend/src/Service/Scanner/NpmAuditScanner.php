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
        $cmd = PHP_OS_FAMILY === 'Windows' ? 'where npm 2>nul' : 'which npm 2>/dev/null';
        exec($cmd, $o, $c);
        return $c === 0;
    }

    public function scan(string $projectPath): array
    {
        if (!file_exists("{$projectPath}/package.json")) {
            $this->logger->warning('package.json not found', ['path' => $projectPath]);
            return [];
        }

        $null = PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null';
        exec(
            sprintf('cd %s && npm audit --json 2>%s', escapeshellarg($projectPath), $null),
            $out,
            $code
        );

        if ($code > 0 && empty($out)) {
            $this->logger->error('npm audit command failed', ['code' => $code, 'path' => $projectPath]);
            return [];
        }

        $json = json_decode(implode("\n", $out), true);
        if (!$json || json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('npm audit JSON parse error', [
                'error' => json_last_error_msg(),
                'path' => $projectPath
            ]);
            return [];
        }

        $vulnerabilities = $json['vulnerabilities'] ?? [];
        if (empty($vulnerabilities)) {
            return [];
        }

        $results = [];
        foreach ($vulnerabilities as $pkg => $data) {
            $viaList = $data['via'] ?? [];
            
            // Handle single vulnerability (not array) or multiple
            if (isset($viaList) && !is_array($viaList)) {
                $viaList = [$viaList];
            }
            
            foreach ($viaList as $via) {
                if (!is_array($via)) continue;
                
                $results[] = new VulnerabilityDTO(
                    toolSource:    $this->getName(),
                    ruleId:        (string) ($via['source'] ?? "npm-{$pkg}"),
                    message:       sprintf('[%s] %s — %s', $pkg, $via['title'] ?? 'Vulnerable dependency', $via['url'] ?? ''),
                    severity:      Severity::fromToolLevel($via['severity'] ?? $data['severity'] ?? 'info'),
                    filePath:      'package.json',
                    codeSnippet:   "Package: {$pkg}@" . ($data['range'] ?? '?'),
                    owaspCategory: OwaspCategory::A03_SUPPLY_CHAIN_FAILURES,
                );
            }
        }
        return $results;
    }
}

