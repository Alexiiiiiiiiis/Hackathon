<?php
namespace App\Tests\Unit\Scanner;

use App\Enum\OwaspCategory;
use App\Enum\Severity;
use App\Service\Scanner\SemgrepScanner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SemgrepScannerTest extends TestCase
{
    private SemgrepScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new SemgrepScanner(new NullLogger());
    }

    public function testGetName(): void
    {
        $this->assertSame('semgrep', $this->scanner->getName());
    }

    public function testScanReturnsEmptyIfNotAvailable(): void
    {
        if ($this->scanner->isAvailable()) {
            $this->markTestSkipped('Semgrep est installé, test non applicable');
        }

        $result = $this->scanner->scan(sys_get_temp_dir());
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testIsAvailableReturnsBool(): void
    {
        $this->assertIsBool($this->scanner->isAvailable());
    }

    /**
     * Test du parsing via un fichier JSON simulé (sans lancer Semgrep)
     * On utilise la réflexion pour accéder à parseResult()
     */
    public function testParseResultMapsCorrectly(): void
    {
        $rawResult = [
            'check_id' => 'php.injection.sql',
            'path'     => 'src/Controller/UserController.php',
            'start'    => ['line' => 42],
            'extra'    => [
                'message'  => 'SQL injection vulnerability',
                'severity' => 'ERROR',
                'lines'    => '$query = "SELECT * FROM users WHERE id = " . $id;',
            ],
        ];

        $method = new \ReflectionMethod($this->scanner, 'parseResult');

        $dto = $method->invoke($this->scanner, $rawResult);

        $this->assertSame('semgrep', $dto->toolSource);
        $this->assertSame('php.injection.sql', $dto->ruleId);
        $this->assertSame('SQL injection vulnerability', $dto->message);
        $this->assertSame(Severity::CRITICAL, $dto->severity);
        $this->assertSame('src/Controller/UserController.php', $dto->filePath);
        $this->assertSame(42, $dto->line);
        $this->assertSame(OwaspCategory::A05_INJECTION, $dto->owaspCategory);
    }

    /** @dataProvider ruleOwaspProvider */
    public function testMapRuleToOwasp(string $ruleId, OwaspCategory $expected): void
    {
        $method = new \ReflectionMethod($this->scanner, 'mapRuleToOwasp');
        $result = $method->invoke($this->scanner, $ruleId);
        $this->assertSame($expected, $result);
    }

    public static function ruleOwaspProvider(): array
    {
        return [
            ['php.sqli.detect',       OwaspCategory::A05_INJECTION],
            ['python.xss.template',   OwaspCategory::A05_INJECTION],
            ['generic.secret.detect', OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES],
            ['php.hardcoded.password',OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES],
            ['js.cors.misconfigured', OwaspCategory::A01_BROKEN_ACCESS_CONTROL],
            ['php.auth.jwt',          OwaspCategory::A07_AUTHENTICATION_FAILURES],
            ['generic.config.debug',  OwaspCategory::A02_SECURITY_MISCONFIGURATION],
            ['js.serial.unsafe',      OwaspCategory::A08_INTEGRITY_FAILURES],
            ['completely.random',     OwaspCategory::UNKNOWN],
        ];
    }
}