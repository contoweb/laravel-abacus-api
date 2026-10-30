<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use BadMethodCallException;
use Contoweb\AbacusApi\AbacusODataQueryBuilder;
use Contoweb\AbacusApi\Tests\Fixtures\TestScopedBatch;
use Contoweb\AbacusApi\Tests\Fixtures\TestScopedProduct;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class AbacusModelScopesTest extends TestCase
{
    #[Test]
    public function it_calls_attribute_scope_on_query_builder(): void
    {
        $query = TestScopedBatch::query()->whereBatchNumber('A1')->toODataQuery();

        $this->assertEquals("BatchNumber ge 'A1' and BatchNumber le 'A1z'", $query['$filter']);
    }

    #[Test]
    public function it_calls_attribute_scope_statically_on_model(): void
    {
        $builder = TestScopedBatch::whereBatchNumber('A1');

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $builder);
        $this->assertEquals("BatchNumber ge 'A1' and BatchNumber le 'A1z'", $builder->toODataQuery()['$filter']);
    }

    #[Test]
    public function it_chains_scopes_with_regular_query_methods(): void
    {
        $query = TestScopedBatch::select(['BatchNumber'])
            ->whereBatchNumber('A1')
            ->whereVariant(3)
            ->where('ProductId', 'eq', 7)
            ->toODataQuery();

        $this->assertEquals('BatchNumber', $query['$select']);
        $this->assertEquals("BatchNumber ge 'A1' and BatchNumber le 'A1z' and VariantId eq 3 and ProductId eq 7", $query['$filter']);
    }

    #[Test]
    public function it_returns_the_builder_when_scope_returns_nothing(): void
    {
        $builder = TestScopedBatch::query();

        $this->assertSame($builder, $builder->whereBatchNumber('A1'));
    }

    #[Test]
    public function it_supports_scope_prefix_methods_on_query_builder(): void
    {
        $query = TestScopedBatch::query()->active()->toODataQuery();

        $this->assertEquals('Active eq true', $query['$filter']);
    }

    #[Test]
    public function it_does_not_call_private_scopes(): void
    {
        $this->expectException(BadMethodCallException::class);

        TestScopedBatch::query()->privateScope();
    }

    #[Test]
    public function it_does_not_call_methods_without_scope_attribute(): void
    {
        $this->expectException(BadMethodCallException::class);

        TestScopedBatch::query()->notAScope();
    }

    #[Test]
    public function it_throws_for_unknown_methods_on_query_builder(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method '.AbacusODataQueryBuilder::class.'::doesNotExist()');

        TestSubject::query()->doesNotExist();
    }

    #[Test]
    public function it_builds_constrained_nested_expand_with_scopes(): void
    {
        $query = TestScopedProduct::select(['Name'])
            ->expand('Batches', fn (AbacusODataQueryBuilder $batches) => $batches
                ->select(['BatchNumber', 'VariantId'])
                ->expand('Assign($select=Id)')
                ->whereBatchNumber('A1')
                ->whereVariant(3))
            ->toODataQuery();

        $this->assertEquals(
            "Batches(\$select=BatchNumber,VariantId;\$expand=Assign(\$select=Id);\$filter=BatchNumber ge 'A1' and BatchNumber le 'A1z' and VariantId eq 3)",
            $query['$expand']
        );
    }

    #[Test]
    public function it_builds_constrained_nested_expand_statically_on_model(): void
    {
        $query = TestScopedProduct::expand('Batches', fn (AbacusODataQueryBuilder $batches) => $batches->whereVariant(3))
            ->toODataQuery();

        $this->assertEquals('Batches($filter=VariantId eq 3)', $query['$expand']);
    }

    #[Test]
    public function it_keeps_multiple_string_expands_statically_on_model(): void
    {
        $query = TestSubject::expand('A', 'B')->toODataQuery();

        $this->assertEquals('A,B', $query['$expand']);
    }

    #[Test]
    public function it_passes_the_related_models_query_builder_to_the_expand_closure(): void
    {
        $resolved = null;

        TestScopedProduct::query()->expand('Batches', function (AbacusODataQueryBuilder $query) use (&$resolved) {
            $resolved = $query;
        });

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $resolved);
        $this->assertTrue($resolved->hasNamedScope('whereBatchNumber'));
    }

    #[Test]
    public function it_renders_constrained_expand_without_options_as_plain_relation(): void
    {
        $query = TestScopedProduct::query()
            ->expand('Batches', fn (AbacusODataQueryBuilder $batches) => $batches)
            ->toODataQuery();

        $this->assertEquals('Batches', $query['$expand']);
    }

    #[Test]
    public function it_combines_string_and_constrained_expands(): void
    {
        $query = TestScopedProduct::query()
            ->expand('Variants($select=Id)')
            ->expand('Batches', fn (AbacusODataQueryBuilder $batches) => $batches->whereVariant(1))
            ->expand('Designations')
            ->toODataQuery();

        $this->assertEquals('Variants($select=Id),Batches($filter=VariantId eq 1),Designations', $query['$expand']);
    }

    #[Test]
    public function it_keeps_plain_string_and_array_expands_unchanged(): void
    {
        $string = TestSubject::query()->expand('A($select=Id)')->toODataQuery();
        $multiple = TestSubject::query()->expand('A', 'B')->toODataQuery();
        $array = TestSubject::query()->expand(['A', 'B($select=Id)'])->toODataQuery();

        $this->assertEquals('A($select=Id)', $string['$expand']);
        $this->assertEquals('A,B', $multiple['$expand']);
        $this->assertEquals('A,B($select=Id)', $array['$expand']);
    }

    #[Test]
    public function it_throws_when_constrained_expand_relation_is_not_cast_to_a_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestScopedProduct::query()->expand('Name', fn (AbacusODataQueryBuilder $query) => $query);
    }

    #[Test]
    public function it_throws_when_constrained_expand_receives_an_array(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestScopedProduct::query()->expand(['Batches'], fn (AbacusODataQueryBuilder $query) => $query);
    }

    #[Test]
    public function it_sets_top_on_the_query_builder_and_returns_the_builder(): void
    {
        $builder = TestScopedBatch::query();

        $this->assertSame($builder, $builder->top(3));
        $this->assertEquals(3, $builder->toODataQuery()['$top']);
    }

    #[Test]
    public function it_sets_top_statically_on_the_model(): void
    {
        $builder = TestScopedBatch::top(5);

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $builder);
        $this->assertEquals(5, $builder->toODataQuery()['$top']);
    }

    #[Test]
    public function it_renders_top_inside_a_nested_expand(): void
    {
        $query = TestScopedProduct::query()
            ->expand('Batches', fn (AbacusODataQueryBuilder $batches) => $batches->select(['BatchNumber'])->top(3))
            ->toODataQuery();

        $this->assertEquals('Batches($select=BatchNumber;$top=3)', $query['$expand']);
    }

    #[Test]
    public function it_builds_a_nested_expand_inside_a_nested_expand(): void
    {
        $query = TestScopedProduct::query()
            ->expand('Batches', fn (AbacusODataQueryBuilder $batches) => $batches
                ->select(['BatchNumber'])
                ->expand('Assignments', fn (AbacusODataQueryBuilder $assignments) => $assignments
                    ->select(['Id'])
                    ->top(2)))
            ->toODataQuery();

        $this->assertEquals(
            'Batches($select=BatchNumber;$expand=Assignments($select=Id;$top=2))',
            $query['$expand']
        );
    }
}
