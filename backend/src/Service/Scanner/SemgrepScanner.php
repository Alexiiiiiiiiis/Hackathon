<?php
namespace App\Service\Scanner;

use App\DTO\VulnerabilityDTO;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use Psr\Log\LoggerInterface;

class SemgrepScanner implements ScannerInterface
{
    private const RULE_OWASP_MAP = [
        'sql'            => OwaspCategory::A05_INJECTION,
        'sqli'           => OwaspCategory::A05_INJECTION,
        'xss'            => OwaspCategory::A05_INJECTION,
        'injection'      => OwaspCategory::A05_INJECTION,
        'command'        => OwaspCategory::A05_INJECTION,
        'eval'           => OwaspCategory::A05_INJECTION,
        'secret'         => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
        'crypto'         => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
        'password'       => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
        'hardcoded'      => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
        'cors'           => OwaspCategory::A01_BROKEN_ACCESS_CONTROL,
        'access-control' => OwaspCategory::A01_BROKEN_ACCESS_CONTROL,
        'idor'           => OwaspCategory::A01_BROKEN_ACCESS_CONTROL,
        'csrf'           => OwaspCategory::A07_AUTHENTICATION_FAILURES,
        'auth'           => OwaspCategory::A07_AUTHENTICATION_FAILURES,
        'session'        => OwaspCategory::A07_AUTHENTICATION_FAILURES,
        'jwt'            => OwaspCategory::A07_AUTHENTICATION_FAILURES,
        'header'         => OwaspCategory::A02_SECURITY_MISCONFIGURATION,
        'debug'          => OwaspCategory::A02_SECURITY_MISCONFIGURATION,
        'config'         => OwaspCategory::A02_SECURITY_MISCONFIGURATION,
        'log'            => OwaspCategory::A09_LOGGING_FAILURES,
        'exception'      => OwaspCategory::A10_EXCEPTIONAL_CONDITIONS,
        'error'          => OwaspCategory::A10_EXCEPTIONAL_CONDITIONS,
        'deseri'         => OwaspCategory::A08_INTEGRITY_FAILURES,
        'serial'         => OwaspCategory::A08_INTEGRITY_FAILURES,
        'insecure'       => OwaspCategory::A06_INSECURE_DESIGN,
        'validation'     => OwaspCategory::A06_INSECURE_DESIGN,
    ];

    public function __construct(private readonly LoggerInterface $logger) {}

    public function getName(): string { return 'semgrep'; }

    public function isAvailable(): bool
    {
        $cmd = PHP_OS_FAMILY === 'Windows' ? 'where semgrep 2>nul' : 'which semgrep 2>/dev/null';
        exec($cmd, $o, $c);
        return $c === 0;
    }

    public function scan(string $projectPath): array
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null';
        exec(
            sprintf('semgrep --config=auto --json --quiet %s 2>%s', escapeshellarg($projectPath), $null),
            $out, $code
        );
        if ($code > 1) { $this->logger->error('Semgrep error', ['code' => $code]); return []; }
        $json = json_decode(implode("\n", $out), true);
        if (!isset($json['results'])) return [];
        return array_map(fn($r) => $this->parseResult($r), $json['results']);
    }

    private function parseResult(array $r): VulnerabilityDTO
    {
        $ruleId = $r['check_id'] ?? 'unknown';
        return new VulnerabilityDTO(
            toolSource:    $this->getName(),
            ruleId:        $ruleId,
            message:       $r['extra']['message'] ?? 'No description',
            severity:      Severity::fromToolLevel($r['extra']['severity'] ?? 'info'),
            filePath:      $r['path'] ?? null,
            line:          $r['start']['line'] ?? null,
            codeSnippet:   $r['extra']['lines'] ?? null,
            owaspCategory: $this->mapRuleToOwasp($ruleId),
        );
    }

    private function mapRuleToOwasp(string $ruleId): OwaspCategory
    {
        $lower = strtolower($ruleId);
        foreach (self::RULE_OWASP_MAP as $kw => $cat) {
            if (str_contains($lower, $kw)) return $cat;
        }
        return OwaspCategory::UNKNOWN;
    }
}