<?php
namespace App\Service\Scanner;

use App\DTO\VulnerabilityDTO;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

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

    // Variables d'environnement passées à Semgrep pour corriger l'encodage Windows
    private const ENV = [
        'PYTHONUTF8'        => '1',
        'PYTHONIOENCODING'  => 'utf-8',
        'SEMGREP_FORCE_COLOR' => 'false',
    ];

    public function __construct(private readonly LoggerInterface $logger) {}

    public function getName(): string { return 'semgrep'; }

    public function isAvailable(): bool
    {
        $process = new Process(['semgrep', '--version'], null, self::ENV);
        $process->run();
        return $process->isSuccessful();
    }

    public function scan(string $projectPath): array
    {
        $process = new Process([
            'semgrep',
            '--config=auto',
            '--json',
            '--quiet',
            $projectPath,
        ], null, self::ENV);

        $process->setTimeout(300);
        $process->run();

        // Semgrep retourne exit code 1 quand il trouve des vulnérabilités — c'est normal
        if ($process->getExitCode() > 1) {
            $this->logger->error('Semgrep error', [
                'code'   => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);
            return [];
        }

        $json = json_decode($process->getOutput(), true);

        if (!isset($json['results'])) {
            $this->logger->warning('Semgrep: pas de results dans le JSON', [
                'output' => substr($process->getOutput(), 0, 500),
            ]);
            return [];
        }

        $this->logger->info('Semgrep: ' . count($json['results']) . ' findings');

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