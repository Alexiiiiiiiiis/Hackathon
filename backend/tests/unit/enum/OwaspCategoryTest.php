<?php
namespace App\Tests\Unit\Enum;

use App\Enum\OwaspCategory;
use PHPUnit\Framework\TestCase;

class OwaspCategoryTest extends TestCase
{
    public function testAllCasesHaveLabel(): void
    {
        foreach (OwaspCategory::cases() as $cat) {
            $label = $cat->label();
            $this->assertNotEmpty($label, "La catégorie {$cat->value} doit avoir un label");
        }
    }

    public function testAllCasesHaveDescription(): void
    {
        foreach (OwaspCategory::cases() as $cat) {
            // description() ne doit pas lever d'exception
            $this->assertIsString($cat->description());
        }
    }

    public function testLabelContainsCategoryCode(): void
    {
        $this->assertStringContainsString('A01', OwaspCategory::A01_BROKEN_ACCESS_CONTROL->label());
        $this->assertStringContainsString('A05', OwaspCategory::A05_INJECTION->label());
        $this->assertStringContainsString('A10', OwaspCategory::A10_EXCEPTIONAL_CONDITIONS->label());
    }

    public function testInjectionValue(): void
    {
        $this->assertSame('A05', OwaspCategory::A05_INJECTION->value);
    }

    public function testUnknownCategory(): void
    {
        $this->assertSame('UNKNOWN', OwaspCategory::UNKNOWN->value);
        $this->assertSame('Unknown', OwaspCategory::UNKNOWN->label());
    }

    public function testTenRealCategories(): void
    {
        $realCategories = array_filter(
            OwaspCategory::cases(),
            fn($c) => $c !== OwaspCategory::UNKNOWN
        );
        $this->assertCount(10, $realCategories);
    }

    public function testFromValue(): void
    {
        $cat = OwaspCategory::from('A05');
        $this->assertSame(OwaspCategory::A05_INJECTION, $cat);
    }

    public function testTryFromInvalidValue(): void
    {
        $cat = OwaspCategory::tryFrom('INVALID');
        $this->assertNull($cat);
    }
}