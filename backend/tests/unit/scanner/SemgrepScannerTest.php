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

    /**
     * Initialise le scanner Semgrep avant chaque test.
     * Utilise un NullLogger pour éviter les logs pendant les tests.
     */
    protected function setUp(): void
    {
        $this->scanner = new SemgrepScanner(new NullLogger());
    }

    /**
     * Teste que le nom du scanner est correct.
     * Le scanner doit s'identifier comme 'semgrep'.
     */
    public function testGetName(): void
    {
        $this->assertSame('semgrep', $this->scanner->getName());
    }

    /**
     * Teste que le scan retourne un tableau vide si Semgrep n'est pas installé.
     * Si l'outil n'est pas disponible, le scan ne doit pas échouer mais retourner un résultat vide.
     */
    public function testScanReturnsEmptyIfNotAvailable(): void
    {
        if ($this->scanner->isAvailable()) {
            $this->markTestSkipped('Semgrep est installé, test non applicable');
        }

        $result = $this->scanner->scan(sys_get_temp_dir());
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Teste que la méthode isAvailable retourne un booléen.
     * Cette méthode vérifie si Semgrep est installé sur le système.
     */
    public function testIsAvailableReturnsBool(): void
    {
        $this->assertIsBool($this->scanner->isAvailable());
    }

    /**
     * Teste le parsing des résultats Semgrep.
     * Vérifie que les données brutes JSON sont correctement converties en DTO.
     * Utilise la réflexion pour accéder à la méthode privée parseResult().
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

    /**
     * Teste le mapping des règles Semgrep vers les catégories OWASP.
     * Chaque règle Semgrep doit être associée à la bonne catégorie de vulnérabilité.
     * 
     * @dataProvider ruleOwaspProvider - Fournit les cas de test pour chaque règle
     */
    public function testMapRuleToOwasp(string $ruleId, OwaspCategory $expected): void
    {
        $method = new \ReflectionMethod($this->scanner, 'mapRuleToOwasp');
        $result = $method->invoke($this->scanner, $ruleId);
        $this->assertSame($expected, $result);
    }

    /**
     * Fournit les données de test pour le mapping règles OWASP.
     * Chaque entrée contient: [ID de règle Semgrep, catégorie OWASP attendue]
     */
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

