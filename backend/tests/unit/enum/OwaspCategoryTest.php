<?php
namespace App\Tests\Unit\Enum;

use App\Enum\OwaspCategory;
use PHPUnit\Framework\TestCase;

class OwaspCategoryTest extends TestCase
{
    /**
     * Teste que toutes les catégories OWASP ont un label défini.
     * Chaque catégorie doit avoir un label non vide pour l'affichage.
     */
    public function testAllCasesHaveLabel(): void
    {
        foreach (OwaspCategory::cases() as $cat) {
            $label = $cat->label();
            $this->assertNotEmpty($label, "La catégorie {$cat->value} doit avoir un label");
        }
    }

    /**
     * Teste que toutes les catégories OWASP peuvent retourner une description.
     * La méthode description() ne doit pas lever d'exception.
     */
    public function testAllCasesHaveDescription(): void
    {
        foreach (OwaspCategory::cases() as $cat) {
            // description() ne doit pas lever d'exception
            $this->assertIsString($cat->description());
        }
    }

    /**
     * Teste que les labels des catégories contiennent leur code OWASP.
     * Vérifie que A01, A05 et A10 sont présents dans les labels correspondants.
     */
    public function testLabelContainsCategoryCode(): void
    {
        $this->assertStringContainsString('A01', OwaspCategory::A01_BROKEN_ACCESS_CONTROL->label());
        $this->assertStringContainsString('A05', OwaspCategory::A05_INJECTION->label());
        $this->assertStringContainsString('A10', OwaspCategory::A10_EXCEPTIONAL_CONDITIONS->label());
    }

    /**
     * Teste que la valeur de la catégorie A05_INJECTION est correcte.
     * La valeur doit être 'A05' selon le standard OWASP Top 10.
     */
    public function testInjectionValue(): void
    {
        $this->assertSame('A05', OwaspCategory::A05_INJECTION->value);
    }

    /**
     * Teste le comportement de la catégorie UNKNOWN.
     * Cette catégorie est utilisée lorsqu'aucune catégorie OWASP ne correspond.
     */
    public function testUnknownCategory(): void
    {
        $this->assertSame('UNKNOWN', OwaspCategory::UNKNOWN->value);
        $this->assertSame('Unknown', OwaspCategory::UNKNOWN->label());
    }

    /**
     * Teste qu'il y a exactement 10 catégories OWASP réelles.
     * Le standard OWASP Top 10 contient 10 catégories (A01 à A10).
     */
    public function testTenRealCategories(): void
    {
        $realCategories = array_filter(
            OwaspCategory::cases(),
            fn($c) => $c !== OwaspCategory::UNKNOWN
        );
        $this->assertCount(10, $realCategories);
    }

    /**
     * Teste la création d'une catégorie à partir de sa valeur string.
     * La méthode from() doit retourner la catégorie correspondante.
     */
    public function testFromValue(): void
    {
        $cat = OwaspCategory::from('A05');
        $this->assertSame(OwaspCategory::A05_INJECTION, $cat);
    }

    /**
     * Teste le comportement avec une valeur invalide.
     * La méthode tryFrom() doit retourner null pour une valeur inexistante.
     */
    public function testTryFromInvalidValue(): void
    {
        $cat = OwaspCategory::tryFrom('INVALID');
        $this->assertNull($cat);
    }
}

