<?php
namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Entity\ScanResult;
use App\Entity\Vulnerability;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use PHPUnit\Framework\TestCase;

class ScanResultTest extends TestCase
{
    /**
     * Crée une vulnérabilité de test avec la sévérité spécifiée.
     * Utilisé pour les tests de calcul de score.
     */
    private function makeVuln(Severity $severity): Vulnerability
    {
        $v = new Vulnerability();
        $v->setToolSource('semgrep')
          ->setRuleId('test.rule')
          ->setMessage('Test vulnerability')
          ->setSeverity($severity)
          ->setOwaspCategory(OwaspCategory::A05_INJECTION);
        return $v;
    }

    /**
     * Teste que le statut par défaut d'un scan est 'pending'.
     * Un nouveau scan doit être en attente de traitement.
     */
    public function testDefaultStatus(): void
    {
        $scan = new ScanResult();
        $this->assertSame('pending', $scan->getStatus());
    }

    /**
     * Teste que la date de début du scan est automatiquement définie.
     * Le champ startedAt doit être initialisé à la création du ScanResult.
     */
    public function testStartedAtIsSet(): void
    {
        $scan = new ScanResult();
        $this->assertInstanceOf(\DateTimeImmutable::class, $scan->getStartedAt());
    }

    /**
     * Teste le calcul du score sans vulnérabilités.
     * Sans vulnérabilités, le score doit être 100 et la note 'A'.
     */
    public function testComputeScoreNoVulns(): void
    {
        $scan = new ScanResult();
        $scan->computeScore();

        $this->assertSame(100, $scan->getGlobalScore());
        $this->assertSame('A', $scan->getGrade());
    }

    /**
     * Teste le calcul du score avec une vulnérabilité INFO.
     * Une seule vulnérabilité INFO réduit le score de 1 point (note A).
     */
    public function testComputeScoreGradeA(): void
    {
        $scan = new ScanResult();
        $scan->addVulnerability($this->makeVuln(Severity::INFO)); // -1
        $scan->computeScore();

        $this->assertSame(99, $scan->getGlobalScore());
        $this->assertSame('A', $scan->getGrade());
    }

    /**
     * Teste le calcul du score avec deux vulnérabilités HIGH.
     * 2 × HIGH = -24 points, score = 76, note = B
     */
    public function testComputeScoreGradeB(): void
    {
        $scan = new ScanResult();
        // 2x HIGH = -24 → score 76 → B
        $scan->addVulnerability($this->makeVuln(Severity::HIGH));
        $scan->addVulnerability($this->makeVuln(Severity::HIGH));
        $scan->computeScore();

        $this->assertSame(76, $scan->getGlobalScore());
        $this->assertSame('B', $scan->getGrade());
    }

    /**
     * Teste le calcul du score avec cinq vulnérabilités CRITICAL.
     * 5 × CRITICAL = -100 points, score = 0, note = F
     */
    public function testComputeScoreGradeF(): void
    {
        $scan = new ScanResult();
        // 5x CRITICAL = -100 → score 0 → F
        for ($i = 0; $i < 5; $i++) {
            $scan->addVulnerability($this->makeVuln(Severity::CRITICAL));
        }
        $scan->computeScore();

        $this->assertSame(0, $scan->getGlobalScore());
        $this->assertSame('F', $scan->getGrade());
    }

    /**
     * Teste que le score ne peut pas être négatif.
     * Même avec 20 vulnérabilités CRITICAL, le score minimum est 0.
     */
    public function testScoreNeverNegative(): void
    {
        $scan = new ScanResult();
        for ($i = 0; $i < 20; $i++) {
            $scan->addVulnerability($this->makeVuln(Severity::CRITICAL));
        }
        $scan->computeScore();

        $this->assertGreaterThanOrEqual(0, $scan->getGlobalScore());
    }

    /**
     * Teste qu'une vulnérabilité ne peut pas être ajoutée deux fois.
     * Les doublons doivent être évités pour ne pas fausser le score.
     */
    public function testAddVulnerabilityNoDuplicate(): void
    {
        $scan = new ScanResult();
        $vuln = $this->makeVuln(Severity::HIGH);

        $scan->addVulnerability($vuln);
        $scan->addVulnerability($vuln); // double ajout

        $this->assertCount(1, $scan->getVulnerabilities());
    }

    /**
     * Teste que l'ajout d'une vulnérabilité définit la référence arrière.
     * La vulnérabilité doit pointer vers le scan结果 (ScanResult).
     */
    public function testAddVulnerabilitySetsBackReference(): void
    {
        $scan = new ScanResult();
        $vuln = $this->makeVuln(Severity::MEDIUM);
        $scan->addVulnerability($vuln);

        $this->assertSame($scan, $vuln->getScanResult());
    }

    /**
     * Teste la modification du statut du scan.
     * Le statut peut être changé de 'pending' à 'completed'.
     */
    public function testSetStatusCompleted(): void
    {
        $scan = new ScanResult();
        $scan->setStatus('completed');
        $this->assertSame('completed', $scan->getStatus());
    }

    /**
     * Teste le mapping entre le nombre de vulnérabilités et la note.
     * Différentes combinaisons de CRITICAL et HIGH doivent donner différentes notes.
     * 
     * @dataProvider gradeProvider - Fournit les cas de test pour chaque scénario
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('gradeProvider')]
    public function testGradeMapping(int $criticals, int $highs, string $expectedGrade): void
    {
        $scan = new ScanResult();
        for ($i = 0; $i < $criticals; $i++) {
            $scan->addVulnerability($this->makeVuln(Severity::CRITICAL));
        }
        for ($i = 0; $i < $highs; $i++) {
            $scan->addVulnerability($this->makeVuln(Severity::HIGH));
        }
        $scan->computeScore();
        $this->assertSame($expectedGrade, $scan->getGrade());
    }

    /**
     * Fournit les données de test pour le mapping des notes.
     * Chaque entrée contient: [nombre CRITICAL, nombre HIGH, note attendue]
     */
    public static function gradeProvider(): array
    {
        return [
            'A — aucune vuln'          => [0, 0, 'A'],
            'B — 2 high'               => [0, 2, 'B'],
            'F — 5 criticals'          => [5, 0, 'F'],
        ];
    }
}

