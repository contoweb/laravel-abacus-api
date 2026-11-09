<?php

namespace Contoweb\AbacusApi\Tests\Unit\Batch;

use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Contoweb\AbacusApi\Models\AbacusModel;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

class BatchResponseDtoTestModel extends AbacusModel
{
    protected static string $resource = 'Subjects';
}

class BatchResponseDtoTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_mapping_a_response_without_body(): void
    {
        $response = new BatchResponseDto(
            success: true,
            status: 204,
            headers: [],
            body: null,
            modelClass: BatchResponseDtoTestModel::class,
        );

        $this->assertNull($response->mapped());
    }

    #[Test]
    public function it_maps_a_response_with_value_array_to_a_collection(): void
    {
        $response = new BatchResponseDto(
            success: true,
            status: 200,
            headers: [],
            body: ['value' => [['Id' => 1], ['Id' => 2]]],
            modelClass: BatchResponseDtoTestModel::class,
        );

        $mapped = $response->mapped();

        $this->assertInstanceOf(Collection::class, $mapped);
        $this->assertCount(2, $mapped);
        $this->assertInstanceOf(BatchResponseDtoTestModel::class, $mapped->first());
    }

    #[Test]
    public function it_maps_a_response_without_value_array_to_a_single_model(): void
    {
        $response = new BatchResponseDto(
            success: true,
            status: 200,
            headers: [],
            body: ['Id' => 1],
            modelClass: BatchResponseDtoTestModel::class,
        );

        $this->assertInstanceOf(BatchResponseDtoTestModel::class, $response->mapped());
    }
}
