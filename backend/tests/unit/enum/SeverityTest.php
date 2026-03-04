<?php
namespace App\Tests\Unit\Enum;

use App\Enum\Severity;
use PHPUnit\Framework\TestCase;

class SeverityTest extends TestCase
{
    /**
     * Teste la conversion des niveaux de sévérité来自 différents outils de scan.
     * Vérifie que chaque niveau d'outil (critical, error, warning, etc.) est correctement
     * converti en sévérité interne de l'application.
     * 
     * @dataProvider fromToolLevelProvider - Fournit les cas de test pour chaque mapping
     */
    public function testFromToolLevel(string $input, Severity $expected): void
    {
        $this->assertSame($expected, Severity::fromToolLevel($input));
    }

    /**
     * Fournit les données de test pour la conversion des niveaux d'outil.
     * Chaque entrée contient: [niveau outil, sévérité attendue]
     */
    public static function fromToolLevelProvider(): array
    {
        return [
            ['critical', Severity::CRITICAL],
            ['CRITICAL', Severity::CRITICAL],
            ['error',    Severity::CRITICAL],
            ['blocker',  Severity::CRITICAL],
            ['high',     Severity::HIGH],
            ['warning',  Severity::HIGH],
            ['warn',     Severity::HIGH],
            ['medium',   Severity::MEDIUM],
            ['moderate', Severity::MEDIUM],
            ['low',      Severity::LOW],
            ['minor',    Severity::LOW],
            ['info',     Severity::INFO],
            ['unknown',  Severity::INFO],
            ['',         Severity::INFO],
        ];
    }

    /**
     * Teste que les points de pénalité sont corrects pour chaque sévérité.
     * Ces points sont utilisés pour calculer le score de sécurité global.
     * CRITICAL = 20 points, HIGH = 12, MEDIUM = 7, LOW = 3, INFO = 1
     */
    public function testPenaltyPoints(): void
    {
        $this->assertSame(20, Severity::CRITICAL->penaltyPoints());
        $this->assertSame(12, Severity::HIGH->penaltyPoints());
        $this->assertSame(7,  Severity::MEDIUM->penaltyPoints());
        $this->assertSame(3,  Severity::LOW->penaltyPoints());
        $this->assertSame(1,  Severity::INFO->penaltyPoints());
    }

    /**
     * Teste que toutes les sévérités ont une pénalité positive.
     * Chaque niveau de sévérité doit contribuer au score de sécurité.
     */
    public function testAllCasesHavePositivePenalty(): void
    {
        foreach (Severity::cases() as $s) {
            $this->assertGreaterThan(0, $s->penaltyPoints(), "Severity {$s->value} doit avoir une pénalité > 0");
        }
    }

    /**
     * Teste les valeurs de chaque sévérité.
     * Les valeurs sont utilisées pour la sérialisation et le stockage en base de données.
     */
    public function testValues(): void
    {
        $this->assertSame('critical', Severity::CRITICAL->value);
        $this->assertSame('high',     Severity::HIGH->value);
        $this->assertSame('medium',   Severity::MEDIUM->value);
        $this->assertSame('low',      Severity::LOW->value);
        $this->assertSame('info',     Severity::INFO->value);
    }

    /**
     * Teste que CRITICAL est plus élevé que HIGH en termes de pénalité.
     * Cela garantit que les vulnérabilités critiques ont un impact plus important sur le score.
     */
    public function testCriticalIsHigherThanHigh(): void
    {
        $this->assertGreaterThan(
            Severity::HIGH->penaltyPoints(),
            Severity::CRITICAL->penaltyPoints()
        );
    }
}

