<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Enums\ODataEnum;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\ODataQueryState;
use Contoweb\AbacusApi\ODataQueryString;
use Contoweb\AbacusApi\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class ODataQueryStateTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusODataClient;
    }

    #[Test]
    public function it_sets_simple_entity_id(): void
    {
        $state = new ODataQueryState;
        $state->id(42);

        $path = $state->buildPathWithId($this->client, 'Subjects');

        $this->assertStringContainsString('Subjects(42)', $path);
    }

    #[Test]
    public function it_sets_string_entity_id(): void
    {
        $state = new ODataQueryState;
        $state->id('abc-123');

        $path = $state->buildPathWithId($this->client, 'Subjects');

        $this->assertStringContainsString('Subjects(abc-123)', $path);
    }

    #[Test]
    public function it_sets_composite_key(): void
    {
        $state = new ODataQueryState;
        $state->id([
            'BatchNumber' => '5436',
            'ProductId' => 12276,
            'VariantId' => 0,
        ]);

        $path = $state->buildPathWithId($this->client, 'Products');

        $this->assertStringContainsString("BatchNumber='5436'", $path);
        $this->assertStringContainsString('ProductId=12276', $path);
        $this->assertStringContainsString('VariantId=0', $path);
    }

    #[Test]
    public function it_builds_simple_where_clause(): void
    {
        $state = new ODataQueryState;
        $state->where('LastName', 'eq', 'Müller');

        $query = $state->buildODataQuery();

        $this->assertEquals("LastName eq 'Müller'", $query['$filter']);
    }

    #[Test]
    public function it_builds_where_with_enum_operator(): void
    {
        $state = new ODataQueryState;
        $state->where('Age', ODataOperator::GREATER_THAN, 18);

        $query = $state->buildODataQuery();

        $this->assertEquals('Age gt 18', $query['$filter']);
    }

    #[Test]
    public function it_builds_multiple_where_clauses_with_and(): void
    {
        $state = new ODataQueryState;
        $state->where('LastName', 'eq', 'Müller')
            ->where('Age', 'gt', 18);

        $query = $state->buildODataQuery();

        $this->assertEquals("LastName eq 'Müller' and Age gt 18", $query['$filter']);
    }

    #[Test]
    public function it_builds_where_equals_convenience_method(): void
    {
        $state = new ODataQueryState;
        $state->whereEquals('Status', 'Active');

        $query = $state->buildODataQuery();

        $this->assertEquals("Status eq 'Active'", $query['$filter']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Operator 'invalid' not supported");

        $state = new ODataQueryState;
        $state->where('Name', 'invalid', 'Test');
    }

    #[Test]
    public function it_supports_all_valid_operators(): void
    {
        $state = new ODataQueryState;

        $state->where('Field1', 'eq', 1)
            ->where('Field2', 'lt', 2)
            ->where('Field3', 'gt', 3)
            ->where('Field4', 'le', 4)
            ->where('Field5', 'ge', 5);

        $query = $state->buildODataQuery();

        $this->assertStringContainsString('Field1 eq 1', $query['$filter']);
        $this->assertStringContainsString('Field2 lt 2', $query['$filter']);
        $this->assertStringContainsString('Field3 gt 3', $query['$filter']);
        $this->assertStringContainsString('Field4 le 4', $query['$filter']);
        $this->assertStringContainsString('Field5 ge 5', $query['$filter']);
    }

    #[Test]
    public function it_formats_string_values_with_quotes(): void
    {
        $state = new ODataQueryState;
        $state->where('Name', 'eq', 'Test String');

        $query = $state->buildODataQuery();

        $this->assertStringContainsString("'Test String'", $query['$filter']);
    }

    #[Test]
    public function it_escapes_single_quotes_in_string_values(): void
    {
        $state = new ODataQueryState;
        $state->where('Name', 'eq', "O'Brien");

        $query = $state->buildODataQuery();

        $this->assertStringContainsString("'O''Brien'", $query['$filter']);
    }

    #[Test]
    public function it_formats_boolean_values(): void
    {
        $state = new ODataQueryState;
        $state->where('IsActive', 'eq', true)
            ->where('IsDeleted', 'eq', false);

        $query = $state->buildODataQuery();

        $this->assertStringContainsString('IsActive eq true', $query['$filter']);
        $this->assertStringContainsString('IsDeleted eq false', $query['$filter']);
    }

    #[Test]
    public function it_formats_null_values(): void
    {
        $state = new ODataQueryState;
        $state->where('MiddleName', 'eq', null);

        $query = $state->buildODataQuery();

        $this->assertStringContainsString('MiddleName eq null', $query['$filter']);
    }

    #[Test]
    public function it_formats_numeric_values(): void
    {
        $state = new ODataQueryState;
        $state->where('Age', 'eq', 42);

        $query = $state->buildODataQuery();

        $this->assertEquals('Age eq 42', $query['$filter']);
    }

    #[Test]
    public function it_formats_datetime_values(): void
    {
        $date = new \DateTime('2024-01-15 14:30:00');
        $state = new ODataQueryState;
        $state->where('CreatedAt', 'gt', $date);

        $query = $state->buildODataQuery();

        $this->assertStringContainsString('2024-01-15T14:30:00Z', $query['$filter']);
    }

    #[Test]
    public function it_formats_odata_enum_values(): void
    {
        $enum = ODataEnum::make('ch.abacus.orde.ProductType', 'Article');
        $state = new ODataQueryState;
        $state->where('Type', 'eq', $enum);

        $query = $state->buildODataQuery();

        $this->assertEquals("Type eq ch.abacus.orde.ProductType'Article'", $query['$filter']);
    }

    #[Test]
    public function it_formats_odata_enum_values_via_static_helper(): void
    {
        $state = new ODataQueryState;
        $state->where('Type', 'eq', ODataQueryString::enum('ch.abacus.orde.ProductType', 'Article'));

        $query = $state->buildODataQuery();

        $this->assertEquals("Type eq ch.abacus.orde.ProductType'Article'", $query['$filter']);
    }

    #[Test]
    public function it_builds_select_query_with_array(): void
    {
        $state = new ODataQueryState;
        $state->select(['Id', 'Name', 'Email']);

        $query = $state->buildODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_builds_select_query_with_varargs(): void
    {
        $state = new ODataQueryState;
        $state->select('Id', 'Name', 'Email');

        $query = $state->buildODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_merges_multiple_select_calls(): void
    {
        $state = new ODataQueryState;
        $state->select('Id', 'Name')
            ->select('Email');

        $query = $state->buildODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_builds_top_query(): void
    {
        $state = new ODataQueryState;
        $state->top(10);

        $query = $state->buildODataQuery();

        $this->assertEquals(10, $query['$top']);
    }

    #[Test]
    public function it_builds_limit_query_as_alias_for_top(): void
    {
        $state = new ODataQueryState;
        $state->limit(5);

        $query = $state->buildODataQuery();

        $this->assertEquals(5, $query['$top']);
    }

    #[Test]
    public function it_builds_take_query_as_alias_for_top(): void
    {
        $state = new ODataQueryState;
        $state->take(20);

        $query = $state->buildODataQuery();

        $this->assertEquals(20, $query['$top']);
    }

    #[Test]
    public function it_builds_order_by_ascending(): void
    {
        $state = new ODataQueryState;
        $state->orderBy('LastName', 'asc');

        $query = $state->buildODataQuery();

        $this->assertEquals('LastName asc', $query['$orderby']);
    }

    #[Test]
    public function it_builds_order_by_descending(): void
    {
        $state = new ODataQueryState;
        $state->orderBy('CreatedAt', 'desc');

        $query = $state->buildODataQuery();

        $this->assertEquals('CreatedAt desc', $query['$orderby']);
    }

    #[Test]
    public function it_defaults_order_by_to_ascending(): void
    {
        $state = new ODataQueryState;
        $state->orderBy('Name');

        $query = $state->buildODataQuery();

        $this->assertEquals('Name asc', $query['$orderby']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_order_direction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Direction must be 'asc' or 'desc'");

        $state = new ODataQueryState;
        $state->orderBy('Name', 'invalid');
    }

    #[Test]
    public function it_overrides_previous_order_by(): void
    {
        $state = new ODataQueryState;
        $state->orderBy('FirstName', 'asc')
            ->orderBy('LastName', 'desc');

        $query = $state->buildODataQuery();

        $this->assertEquals('LastName desc', $query['$orderby']);
    }

    #[Test]
    public function it_builds_expand_query_with_array(): void
    {
        $state = new ODataQueryState;
        $state->expand(['Addresses', 'Contacts']);

        $query = $state->buildODataQuery();

        $this->assertEquals('Addresses,Contacts', $query['$expand']);
    }

    #[Test]
    public function it_builds_expand_query_with_varargs(): void
    {
        $state = new ODataQueryState;
        $state->expand('Addresses', 'Contacts');

        $query = $state->buildODataQuery();

        $this->assertEquals('Addresses,Contacts', $query['$expand']);
    }

    #[Test]
    public function it_merges_multiple_expand_calls(): void
    {
        $state = new ODataQueryState;
        $state->expand('Addresses')
            ->expand('Contacts', 'Orders');

        $query = $state->buildODataQuery();

        $this->assertEquals('Addresses,Contacts,Orders', $query['$expand']);
    }

    #[Test]
    public function it_builds_complete_query_with_all_parameters(): void
    {
        $state = new ODataQueryState;
        $state->where('IsActive', 'eq', true)
            ->select(['Id', 'Name'])
            ->orderBy('Name', 'asc')
            ->top(10)
            ->expand('Addresses');

        $query = $state->buildODataQuery();

        $this->assertEquals('IsActive eq true', $query['$filter']);
        $this->assertEquals('Id,Name', $query['$select']);
        $this->assertEquals('Name asc', $query['$orderby']);
        $this->assertEquals(10, $query['$top']);
        $this->assertEquals('Addresses', $query['$expand']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_parameters_set(): void
    {
        $state = new ODataQueryState;

        $query = $state->buildODataQuery();

        $this->assertEmpty($query);
    }

    #[Test]
    public function to_odata_query_is_alias_for_build_odata_query(): void
    {
        $state = new ODataQueryState;
        $state->where('Status', 'eq', 'Active');

        $this->assertEquals($state->buildODataQuery(), $state->toODataQuery());
    }

    #[Test]
    public function it_supports_laravel_equals_operator(): void
    {
        $state = new ODataQueryState;
        $state->where('LastName', '=', 'Müller');

        $query = $state->buildODataQuery();

        $this->assertEquals("LastName eq 'Müller'", $query['$filter']);
    }

    #[Test]
    public function it_supports_laravel_greater_than_operator(): void
    {
        $state = new ODataQueryState;
        $state->where('Age', '>', 18);

        $query = $state->buildODataQuery();

        $this->assertEquals('Age gt 18', $query['$filter']);
    }

    #[Test]
    public function it_supports_laravel_greater_than_or_equal_operator(): void
    {
        $state = new ODataQueryState;
        $state->where('Score', '>=', 80);

        $query = $state->buildODataQuery();

        $this->assertEquals('Score ge 80', $query['$filter']);
    }

    #[Test]
    public function it_supports_laravel_less_than_operator(): void
    {
        $state = new ODataQueryState;
        $state->where('Price', '<', 100);

        $query = $state->buildODataQuery();

        $this->assertEquals('Price lt 100', $query['$filter']);
    }

    #[Test]
    public function it_supports_laravel_less_than_or_equal_operator(): void
    {
        $state = new ODataQueryState;
        $state->where('Quantity', '<=', 50);

        $query = $state->buildODataQuery();

        $this->assertEquals('Quantity le 50', $query['$filter']);
    }

    #[Test]
    public function it_supports_mixed_odata_and_laravel_operators(): void
    {
        $state = new ODataQueryState;
        $state->where('IsActive', 'eq', true)
            ->where('Age', '>', 18)
            ->where('Score', 'ge', 80);

        $query = $state->buildODataQuery();

        $this->assertStringContainsString('IsActive eq true', $query['$filter']);
        $this->assertStringContainsString('Age gt 18', $query['$filter']);
        $this->assertStringContainsString('Score ge 80', $query['$filter']);
    }

    #[Test]
    public function it_shows_laravel_operators_in_error_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('(or Laravel equivalents: =, >, >=, <, <=)');

        $state = new ODataQueryState;
        $state->where('Name', 'invalid', 'Test');
    }
}
