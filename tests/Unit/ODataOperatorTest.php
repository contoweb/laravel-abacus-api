<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ODataOperatorTest extends TestCase
{
    #[Test]
    public function it_has_equals_operator(): void
    {
        $this->assertEquals('eq', ODataOperator::EQUALS->value);
    }

    #[Test]
    public function it_has_greater_than_operator(): void
    {
        $this->assertEquals('gt', ODataOperator::GREATER_THAN->value);
    }

    #[Test]
    public function it_has_greater_than_or_equal_operator(): void
    {
        $this->assertEquals('ge', ODataOperator::GREATER_THAN_OR_EQUAL->value);
    }

    #[Test]
    public function it_has_less_than_operator(): void
    {
        $this->assertEquals('lt', ODataOperator::LESS_THAN->value);
    }

    #[Test]
    public function it_has_less_than_or_equal_operator(): void
    {
        $this->assertEquals('le', ODataOperator::LESS_THAN_OR_EQUAL->value);
    }

    #[Test]
    public function it_returns_all_operator_values(): void
    {
        $values = ODataOperator::values();

        $this->assertIsArray($values);
        $this->assertCount(5, $values);
        $this->assertContains('eq', $values);
        $this->assertContains('gt', $values);
        $this->assertContains('ge', $values);
        $this->assertContains('lt', $values);
        $this->assertContains('le', $values);
    }

    #[Test]
    public function it_validates_valid_operators(): void
    {
        $this->assertTrue(ODataOperator::isValid('eq'));
        $this->assertTrue(ODataOperator::isValid('gt'));
        $this->assertTrue(ODataOperator::isValid('ge'));
        $this->assertTrue(ODataOperator::isValid('lt'));
        $this->assertTrue(ODataOperator::isValid('le'));
    }

    #[Test]
    public function it_validates_invalid_operators(): void
    {
        $this->assertFalse(ODataOperator::isValid('invalid'));
        $this->assertFalse(ODataOperator::isValid('ne'));
        $this->assertFalse(ODataOperator::isValid('or'));
        $this->assertFalse(ODataOperator::isValid(''));
    }

    #[Test]
    public function it_can_be_used_in_comparisons(): void
    {
        $operator = ODataOperator::EQUALS;

        $this->assertSame(ODataOperator::EQUALS, $operator);
        $this->assertEquals('eq', $operator->value);
    }

    #[Test]
    public function it_returns_all_cases(): void
    {
        $cases = ODataOperator::cases();

        $this->assertIsArray($cases);
        $this->assertCount(5, $cases);
        $this->assertContainsOnlyInstancesOf(ODataOperator::class, $cases);
    }

    #[Test]
    public function enum_values_match_odata_standard(): void
    {
        /* Verify that enum values match OData specification */
        $expectedMapping = [
            'EQUALS' => 'eq',
            'GREATER_THAN' => 'gt',
            'GREATER_THAN_OR_EQUAL' => 'ge',
            'LESS_THAN' => 'lt',
            'LESS_THAN_OR_EQUAL' => 'le',
        ];

        foreach ($expectedMapping as $constant => $odataValue) {
            $enum = constant(ODataOperator::class.'::'.$constant);
            $this->assertEquals($odataValue, $enum->value);
        }
    }

    #[Test]
    public function it_converts_laravel_operators_to_odata(): void
    {
        $this->assertEquals('eq', ODataOperator::fromLaravel('='));
        $this->assertEquals('gt', ODataOperator::fromLaravel('>'));
        $this->assertEquals('ge', ODataOperator::fromLaravel('>='));
        $this->assertEquals('lt', ODataOperator::fromLaravel('<'));
        $this->assertEquals('le', ODataOperator::fromLaravel('<='));
    }

    #[Test]
    public function it_returns_null_for_unknown_laravel_operators(): void
    {
        $this->assertNull(ODataOperator::fromLaravel('eq'));
        $this->assertNull(ODataOperator::fromLaravel('!='));
        $this->assertNull(ODataOperator::fromLaravel('<>'));
        $this->assertNull(ODataOperator::fromLaravel('like'));
        $this->assertNull(ODataOperator::fromLaravel(''));
    }

    #[Test]
    public function it_validates_laravel_operators_as_valid(): void
    {
        $this->assertTrue(ODataOperator::isValid('='));
        $this->assertTrue(ODataOperator::isValid('>'));
        $this->assertTrue(ODataOperator::isValid('>='));
        $this->assertTrue(ODataOperator::isValid('<'));
        $this->assertTrue(ODataOperator::isValid('<='));
    }
}
