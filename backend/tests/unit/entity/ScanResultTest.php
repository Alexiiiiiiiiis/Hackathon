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

    public function testDefaultStatus(): void
    {
        $scan = new ScanResult();
        $this->assertSame('pending', $scan->getStatus());
    }

    public function testStartedAtIsSet(): void
    {
        $scan = new ScanResult();
        $this->assertInstanceOf(\DateTimeImmutable::class, $scan->getStartedAt());
    }

    public function testComputeScoreNoVulns(): void
    {
        $scan = new ScanResult();
        $scan->computeScore();

        $this->assertSame(100, $scan->getGlobalScore());
        $this->assertSame('A', $scan->getGrade());
    }

    public function testComputeScoreGradeA(): void
    {
        $scan = new ScanResult();
        $scan->addVulnerability($this->makeVuln(Severity::INFO)); // -1
        $scan->computeScore();

        $this->assertSame(99, $scan->getGlobalScore());
        $this->assertSame('A', $scan->getGrade());
    }

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

    public function testScoreNeverNegative(): void
    {
        $scan = new ScanResult();
        for ($i = 0; $i < 20; $i++) {
            $scan->addVulnerability($this->makeVuln(Severity::CRITICAL));
        }
        $scan->computeScore();

        $this->assertGreaterThanOrEqual(0, $scan->getGlobalScore());
    }

    public function testAddVulnerabilityNoDuplicate(): void
    {
        $scan = new ScanResult();
        $vuln = $this->makeVuln(Severity::HIGH);

        $scan->addVulnerability($vuln);
        $scan->addVulnerability($vuln); // double ajout

        $this->assertCount(1, $scan->getVulnerabilities());
    }

    public function testAddVulnerabilitySetsBackReference(): void
    {
        $scan = new ScanResult();
        $vuln = $this->makeVuln(Severity::MEDIUM);
        $scan->addVulnerability($vuln);

        $this->assertSame($scan, $vuln->getScanResult());
    }

    public function testSetStatusCompleted(): void
    {
        $scan = new ScanResult();
        $scan->setStatus('completed');
        $this->assertSame('completed', $scan->getStatus());
    }

    /** @dataProvider gradeProvider */
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

    public static function gradeProvider(): array
    {
        return [
            'A — aucune vuln'          => [0, 0, 'A'],
            'B — 2 high'               => [0, 2, 'B'],
            'F — 5 criticals'          => [5, 0, 'F'],
        ];
    }
}