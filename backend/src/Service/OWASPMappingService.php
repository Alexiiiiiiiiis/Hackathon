<?php
namespace App\Service;

use App\DTO\VulnerabilityDTO;
use App\Entity\ScanResult;
use App\Entity\Vulnerability;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use Doctrine\ORM\EntityManagerInterface;

class OWASPMappingService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    private const FIXES = [
        'sql' => "Utiliser des requetes preparees (PDO prepare/execute).",
        'xss' => "Echapper les sorties (htmlspecialchars/DOMPurify).",
        'password' => "Hasher avec Argon2 ou bcrypt.",
        'secret' => "Deplacer les secrets dans des variables d environnement.",
        'hardcoded' => "Supprimer les valeurs sensibles en dur.",
        'eval' => "Eviter eval et parser les donnees de facon sure.",
        'csrf' => "Ajouter un token CSRF sur les formulaires.",
        'cors' => "Restreindre CORS a une liste blanche explicite.",
        'jwt' => "Verifier signature, expiration et claims.",
        'serial' => "Eviter la deserialisation de donnees non fiables.",
    ];

    public function mapAndPersist(VulnerabilityDTO $dto, ScanResult $scanResult): Vulnerability
    {
        $vuln = new Vulnerability();
        $vuln->setToolSource(substr($dto->toolSource ?? 'unknown', 0, 50))
            ->setRuleId(substr($dto->ruleId ?? 'unknown', 0, 255))
            ->setMessage($dto->message ?? 'No description')
            ->setSeverity($dto->severity)
            ->setFilePath($dto->filePath ? substr($dto->filePath, 0, 512) : null)
            ->setLine($dto->line)
            ->setCodeSnippet($dto->codeSnippet)
            ->setOwaspCategory(
                $dto->owaspCategory !== OwaspCategory::UNKNOWN
                    ? $dto->owaspCategory
                    : $this->inferOwasp($dto->message ?? '', $dto->ruleId ?? '')
            )
            ->setSuggestedFix($this->buildFix($dto));

            $scanResult->addVulnerability($vuln);
            $this->em->persist($vuln);

            return $vuln;
    }

    private function inferOwasp(string $message, string $ruleId): OwaspCategory
    {
        $text = strtolower($message . ' ' . $ruleId);
        
        $map = [
            'sql'        => OwaspCategory::A05_INJECTION,
            'xss'        => OwaspCategory::A05_INJECTION,
            'inject'     => OwaspCategory::A05_INJECTION,
            'eval'       => OwaspCategory::A05_INJECTION,
            'command'    => OwaspCategory::A05_INJECTION,
            'ldap'       => OwaspCategory::A05_INJECTION,
            'password'   => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
            'secret'     => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
            'crypto'     => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
            'md5'        => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
            'sha1'       => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
            'hardcoded'  => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
            'idor'       => OwaspCategory::A01_BROKEN_ACCESS_CONTROL,
            'cors'       => OwaspCategory::A01_BROKEN_ACCESS_CONTROL,
            'access'     => OwaspCategory::A01_BROKEN_ACCESS_CONTROL,
            'header'     => OwaspCategory::A02_SECURITY_MISCONFIGURATION,
            'debug'      => OwaspCategory::A02_SECURITY_MISCONFIGURATION,
            'config'     => OwaspCategory::A02_SECURITY_MISCONFIGURATION,
            'dependenc'  => OwaspCategory::A03_SUPPLY_CHAIN_FAILURES,
            'package'    => OwaspCategory::A03_SUPPLY_CHAIN_FAILURES,
            'cve'        => OwaspCategory::A03_SUPPLY_CHAIN_FAILURES,
            'auth'       => OwaspCategory::A07_AUTHENTICATION_FAILURES,
            'session'    => OwaspCategory::A07_AUTHENTICATION_FAILURES,
            'jwt'        => OwaspCategory::A07_AUTHENTICATION_FAILURES,
            'csrf'       => OwaspCategory::A07_AUTHENTICATION_FAILURES,
            'log'        => OwaspCategory::A09_LOGGING_FAILURES,
            'monitor'    => OwaspCategory::A09_LOGGING_FAILURES,
            'exception'  => OwaspCategory::A10_EXCEPTIONAL_CONDITIONS,
            'error'      => OwaspCategory::A10_EXCEPTIONAL_CONDITIONS,
            'serial'     => OwaspCategory::A08_INTEGRITY_FAILURES,
            'deseri'     => OwaspCategory::A08_INTEGRITY_FAILURES,
            'validation' => OwaspCategory::A06_INSECURE_DESIGN,
            'insecure'   => OwaspCategory::A06_INSECURE_DESIGN,
        ];

    foreach ($map as $kw => $cat) {
        if (str_contains($text, $kw)) {
            return $cat;
        }
    }

    return OwaspCategory::UNKNOWN;
}

    private function buildFix(VulnerabilityDTO $dto): ?string
    {
        $text = strtolower($dto->ruleId . ' ' . $dto->message);
        foreach (self::FIXES as $kw => $fix) {
            if (str_contains($text, $kw)) {
                return $fix;
            }
        }

        return null;
    }

    public function buildStats(ScanResult $scanResult): array
    {
        $vulns = $scanResult->getVulnerabilities()->toArray();
        $bySeverity = array_fill_keys(array_column(Severity::cases(), 'value'), 0);
        $byOwasp = [];

        foreach ($vulns as $v) {
            $bySeverity[$v->getSeverity()->value]++;
            $key = $v->getOwaspCategory()->value;
            $byOwasp[$key] = ($byOwasp[$key] ?? 0) + 1;
        }

        return [
            'totalVulns' => count($vulns),
            'bySeverity' => $bySeverity,
            'byOwasp' => $byOwasp,
            'coveredCategories' => count($byOwasp),
            'score' => $scanResult->getGlobalScore() ?? 0,
            'grade' => $scanResult->getGrade() ?? 'N/A',
        ];
    }
}

