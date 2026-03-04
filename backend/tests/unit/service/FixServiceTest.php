<?php
namespace App\Tests\Unit\Service;

use App\Entity\Fix;
use App\Entity\Project;
use App\Entity\ScanResult;
use App\Entity\Vulnerability;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use App\Service\FixService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FixServiceTest extends TestCase
{
    private FixService $fixService;
    private EntityManagerInterface&MockObject $em;

    /**
     * Initialise le service de correction et le mock de l'EntityManager.
     * Exécuté avant chaque test.
     */
    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->fixService = new FixService($this->em);
    }

    /**
     * Crée une vulnérabilité de test avec tous les champs nécessaires.
     * Inclut un projet, un scan et la vulnérabilité elle-même.
     */
    private function makeVuln(string $snippet = '// code', ?string $suggestedFix = null): Vulnerability
    {
        $project = new Project();
        $project->setName('Test')->setSource('git@test')->setSourceType('github');

        $scan = new ScanResult();
        $scan->setProject($project);

        $v = new Vulnerability();
        $v->setToolSource('semgrep')
          ->setRuleId('php.sql')
          ->setMessage('SQL injection')
          ->setSeverity(Severity::CRITICAL)
          ->setOwaspCategory(OwaspCategory::A05_INJECTION)
          ->setCodeSnippet($snippet)
          ->setSuggestedFix($suggestedFix);
        $scan->addVulnerability($v);

        return $v;
    }

    /**
     * Teste la création d'une entité Fix à partir d'une vulnérabilité.
     * Le service doit créer un objet Fix avec le statut 'pending'.
     */
    public function testGenerateFixCreatesFixEntity(): void
    {
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $vuln = $this->makeVuln('$id = $_GET["id"];', 'Utiliser PDO::prepare()');
        $fix  = $this->fixService->generateFix($vuln);

        $this->assertInstanceOf(Fix::class, $fix);
        $this->assertSame('pending', $fix->getStatus());
    }

    /**
     * Teste que le code original est préservé dans la correction.
     * Le code vulnérable doit être stocké pour référence.
     */
    public function testGenerateFixSetsOriginalCode(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $snippet = '$query = "SELECT * FROM users WHERE id = " . $id;';
        $vuln    = $this->makeVuln($snippet);
        $fix     = $this->fixService->generateFix($vuln);

        $this->assertSame($snippet, $fix->getOriginalCode());
    }

    /**
     * Teste que la suggestion de correction est utilisée si disponible.
     * Si la vulnérabilité a une suggestion, elle doit apparaître dans le code corrigé.
     */
    public function testGenerateFixWithSuggestedFixUsesIt(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $vuln = $this->makeVuln('old code', 'Utiliser PDO::prepare()');
        $fix  = $this->fixService->generateFix($vuln);

        $this->assertStringContainsString('Utiliser PDO::prepare()', $fix->getFixedCode());
    }

    /**
     * Teste le comportement quand aucune suggestion de correction n'est disponible.
     * Un placeholder TODO doit être inséré pour提示手动修复.
     */
    public function testGenerateFixWithoutSuggestedFixHasTodo(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $vuln = $this->makeVuln('old code', null);
        $fix  = $this->fixService->generateFix($vuln);

        $this->assertStringContainsString('TODO', $fix->getFixedCode());
    }

    /**
     * Teste que la référence arrière est correctement établie.
     * La vulnérabilité doit pointer vers sa correction et vice versa.
     */
    public function testGenerateFixSetsVulnBackReference(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $vuln = $this->makeVuln();
        $fix  = $this->fixService->generateFix($vuln);

        $this->assertSame($fix, $vuln->getFix());
        $this->assertSame($vuln, $fix->getVulnerability());
    }

    /**
     * Teste que l'explication contient la catégorie OWASP.
     * Chaque correction doit inclure des informations sur le type de vulnérabilité.
     */
    public function testGenerateFixExplanationContainsOwasp(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $vuln = $this->makeVuln();
        $fix  = $this->fixService->generateFix($vuln);

        $this->assertNotEmpty($fix->getExplanation());
        $this->assertStringContainsString('A05', $fix->getExplanation());
    }

    /**
     * Teste qu'une exception est levée si le fichier à corriger n'existe pas.
     * Le service doit gérer proprement les erreurs de fichier manquant.
     */
    public function testApplyFixThrowsIfFileNotFound(): void
    {
        $this->expectException(\RuntimeException::class);

        $vuln = $this->makeVuln();
        $vuln->setFilePath('/non/existent/file.php');
        $fix = new Fix();
        $fix->setVulnerability($vuln)
            ->setScanResult($vuln->getScanResult())
            ->setOriginalCode('code')
            ->setFixedCode('fixed')
            ->setExplanation('test');

        $this->fixService->applyFix($fix);
    }

    /**
     * Teste le rejet d'une correction.
     * Quand une correction est rejetée, les statuts doivent être mis à jour.
     */
    public function testRejectFixSetsStatus(): void
    {
        $this->em->expects($this->once())->method('flush');

        $vuln = $this->makeVuln();
        $fix  = new Fix();
        $fix->setVulnerability($vuln)
            ->setScanResult($vuln->getScanResult())
            ->setOriginalCode('code')
            ->setFixedCode('fixed')
            ->setExplanation('test');

        $this->fixService->rejectFix($fix);

        $this->assertSame('rejected', $fix->getStatus());
        $this->assertSame('rejected', $vuln->getFixStatus());
    }
}

