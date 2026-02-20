<?php

namespace Contoweb\AbacusApi\Tests\Unit\Batch;

use Contoweb\AbacusApi\Batch\BatchResponseCollection;
use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class BatchResponseCollectionTest extends TestCase
{
    #[Test]
    public function it_can_filter_successful_responses(): void
    {
        $responses = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: true,
                status: 201,
                headers: [],
                body: ['value' => [['id' => 2]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: false,
                status: 404,
                headers: [],
                body: ['error' => ['message' => 'Entity not found']],
                modelClass: 'TestModel',
                error: 'Not found'
            ),
        ]);

        $successful = $responses->successful();

        $this->assertCount(2, $successful);
        $this->assertEquals(200, $successful->first()->status);
        $this->assertEquals(201, $successful->last()->status);
    }

    #[Test]
    public function it_can_filter_failed_responses(): void
    {
        $responses = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: false,
                status: 404,
                headers: [],
                body: ['error' => ['message' => 'Entity not found']],
                modelClass: 'TestModel',
                error: 'Not found'
            ),
            new BatchResponseDto(
                success: false,
                status: 500,
                headers: [],
                body: ['error' => ['message' => 'Internal error']],
                modelClass: 'TestModel',
                error: 'Server error'
            ),
        ]);

        $failed = $responses->failed();

        $this->assertCount(2, $failed);
        $this->assertEquals(404, $failed->first()->status);
        $this->assertEquals(500, $failed->last()->status);
    }

    #[Test]
    public function it_can_check_if_all_responses_are_successful(): void
    {
        $allSuccessful = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: true,
                status: 201,
                headers: [],
                body: ['value' => [['id' => 2]]],
                modelClass: 'TestModel'
            ),
        ]);

        $this->assertTrue($allSuccessful->allSuccessful());

        $mixedResponses = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: false,
                status: 404,
                headers: [],
                body: ['error' => ['message' => 'Entity not found']],
                modelClass: 'TestModel',
                error: 'Not found'
            ),
        ]);

        $this->assertFalse($mixedResponses->allSuccessful());
    }

    #[Test]
    public function it_can_check_if_any_responses_failed(): void
    {
        $allSuccessful = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: true,
                status: 201,
                headers: [],
                body: ['value' => [['id' => 2]]],
                modelClass: 'TestModel'
            ),
        ]);

        $this->assertFalse($allSuccessful->hasFailures());

        $withFailures = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: false,
                status: 404,
                headers: [],
                body: ['error' => ['message' => 'Entity not found']],
                modelClass: 'TestModel',
                error: 'Not found'
            ),
        ]);

        $this->assertTrue($withFailures->hasFailures());
    }

    #[Test]
    public function it_can_extract_single_abacus_models_from_successful_responses(): void
    {
        $model1 = Mockery::mock(\Contoweb\AbacusApi\Models\AbacusModel::class);
        $model2 = Mockery::mock(\Contoweb\AbacusApi\Models\AbacusModel::class);

        $response1 = Mockery::mock(BatchResponseDto::class);
        $response1->shouldReceive('isSuccess')->andReturn(true);
        $response1->shouldReceive('getModels')->andReturn($model1);

        $response2 = Mockery::mock(BatchResponseDto::class);
        $response2->shouldReceive('isSuccess')->andReturn(true);
        $response2->shouldReceive('getModels')->andReturn($model2);

        $response3 = Mockery::mock(BatchResponseDto::class);
        $response3->shouldReceive('isSuccess')->andReturn(false);

        $responses = new BatchResponseCollection([$response1, $response2, $response3]);

        $models = $responses->models();

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount(2, $models);
        $this->assertSame($model1, $models->first());
        $this->assertSame($model2, $models->last());
    }

    #[Test]
    public function it_can_extract_collections_with_abacus_models_from_successful_responses(): void
    {
        $model1 = Mockery::mock(\Contoweb\AbacusApi\Models\AbacusModel::class);
        $model2 = Mockery::mock(\Contoweb\AbacusApi\Models\AbacusModel::class);
        $model3 = Mockery::mock(\Contoweb\AbacusApi\Models\AbacusModel::class);

        $response1 = Mockery::mock(BatchResponseDto::class);
        $response1->shouldReceive('isSuccess')->andReturn(true);
        $response1->shouldReceive('getModels')->andReturn(collect([$model1, $model2]));

        $response2 = Mockery::mock(BatchResponseDto::class);
        $response2->shouldReceive('isSuccess')->andReturn(true);
        $response2->shouldReceive('getModels')->andReturn(collect([$model3]));

        $response3 = Mockery::mock(BatchResponseDto::class);
        $response3->shouldReceive('isSuccess')->andReturn(false);

        $responses = new BatchResponseCollection([$response1, $response2, $response3]);

        $models = $responses->models();

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount(2, $models);

        $this->assertInstanceOf(Collection::class, $models->first());
        $this->assertCount(2, $models->first());
        $this->assertSame($model1, $models->first()[0]);
        $this->assertSame($model2, $models->first()[1]);

        $this->assertInstanceOf(Collection::class, $models->last());
        $this->assertCount(1, $models->last());
        $this->assertSame($model3, $models->last()[0]);
    }

    #[Test]
    public function it_can_extract_errors_from_failed_responses(): void
    {
        $responses = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: false,
                status: 404,
                headers: [],
                body: ['error' => ['message' => 'Entity not found']],
                modelClass: 'TestModel',
                error: 'NotFound'
            ),
            new BatchResponseDto(
                success: false,
                status: 500,
                headers: [],
                body: ['error' => ['message' => 'Internal server error']],
                modelClass: 'TestModel',
                error: 'ServerError'
            ),
        ]);

        $errors = $responses->errors();

        $this->assertInstanceOf(Collection::class, $errors);
        $this->assertCount(2, $errors);

        $firstError = $errors->first();
        $this->assertEquals(404, $firstError['status']);
        $this->assertEquals('NotFound', $firstError['error']);
        $this->assertEquals('Entity not found', $firstError['message']);

        $lastError = $errors->last();
        $this->assertEquals(500, $lastError['status']);
        $this->assertEquals('ServerError', $lastError['error']);
        $this->assertEquals('Internal server error', $lastError['message']);
    }

    #[Test]
    public function it_supports_named_keys(): void
    {
        $responses = new BatchResponseCollection([
            'customer' => new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            'product' => new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 2]]],
                modelClass: 'TestModel'
            ),
        ]);

        $this->assertTrue($responses->has('customer'));
        $this->assertTrue($responses->has('product'));
        $this->assertEquals(200, $responses->get('customer')->status);
        $this->assertEquals(200, $responses->get('product')->status);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_successful_responses(): void
    {
        $responses = new BatchResponseCollection([
            new BatchResponseDto(
                success: false,
                status: 404,
                headers: [],
                body: ['error' => ['message' => 'Entity not found']],
                modelClass: 'TestModel',
                error: 'Not found'
            ),
            new BatchResponseDto(
                success: false,
                status: 500,
                headers: [],
                body: ['error' => ['message' => 'Internal error']],
                modelClass: 'TestModel',
                error: 'Server error'
            ),
        ]);

        $successful = $responses->successful();
        $this->assertTrue($successful->isEmpty());
    }

    #[Test]
    public function it_returns_empty_collection_when_no_failed_responses(): void
    {
        $responses = new BatchResponseCollection([
            new BatchResponseDto(
                success: true,
                status: 200,
                headers: [],
                body: ['value' => [['id' => 1]]],
                modelClass: 'TestModel'
            ),
            new BatchResponseDto(
                success: true,
                status: 201,
                headers: [],
                body: ['value' => [['id' => 2]]],
                modelClass: 'TestModel'
            ),
        ]);

        $failed = $responses->failed();
        $this->assertTrue($failed->isEmpty());
    }
}
