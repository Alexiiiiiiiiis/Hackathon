<?php
namespace App\Tests\Unit\Enum;

use App\Enum\Severity;
use PHPUnit\Framework\TestCase;

class SeverityTest extends TestCase
{
    /** @dataProvider fromToolLevelProvider */
    public function testFromToolLevel(string $input, Severity $expected): void
    {
        $this->assertSame($expected, Severity::fromToolLevel($input));
    }

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

    public function testPenaltyPoints(): void
    {
        $this->assertSame(20, Severity::CRITICAL->penaltyPoints());
        $this->assertSame(12, Severity::HIGH->penaltyPoints());
        $this->assertSame(7,  Severity::MEDIUM->penaltyPoints());
        $this->assertSame(3,  Severity::LOW->penaltyPoints());
        $this->assertSame(1,  Severity::INFO->penaltyPoints());
    }

    public function testAllCasesHavePositivePenalty(): void
    {
        foreach (Severity::cases() as $s) {
            $this->assertGreaterThan(0, $s->penaltyPoints(), "Severity {$s->value} doit avoir une pénalité > 0");
        }
    }

    public function testValues(): void
    {
        $this->assertSame('critical', Severity::CRITICAL->value);
        $this->assertSame('high',     Severity::HIGH->value);
        $this->assertSame('medium',   Severity::MEDIUM->value);
        $this->assertSame('low',      Severity::LOW->value);
        $this->assertSame('info',     Severity::INFO->value);
    }

    public function testCriticalIsHigherThanHigh(): void
    {
        $this->assertGreaterThan(
            Severity::HIGH->penaltyPoints(),
            Severity::CRITICAL->penaltyPoints()
        );
    }
}