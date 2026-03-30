<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Models\AbacusComponent;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/* Test enum for component casting */
enum TestUnitType: string
{
    case METRIC = 'Metric';
    case IMPERIAL = 'Imperial';
}

/* Test enum for status */
enum TestStatus: string
{
    case ACTIVE = 'Active';
    case INACTIVE = 'Inactive';
}

/* Test nested component for component-within-component casting */
class TestDimension extends AbacusComponent
{
    protected array $casts = [
        'value' => 'float',
        'unit' => TestUnitType::class,
    ];
}

/* Test component with various cast types */
class TestMeasurementsWithCasts extends AbacusComponent
{
    protected array $casts = [
        'Length' => 'float',
        'Width' => 'float',
        'Height' => 'float',
        'UnitId' => 'int',
        'IsActive' => 'bool',
        'UnitType' => TestUnitType::class,
        'Status' => TestStatus::class,
        'Metadata' => 'json',
        'Tags' => 'array',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime:Y-m-d H:i',
        'Dimension' => TestDimension::class,
    ];
}

class AbacusComponentTest extends TestCase
{
    #[Test]
    public function it_casts_primitive_types(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'Width' => '5.2',
            'UnitId' => '42',
            'IsActive' => '1',
        ]);

        $this->assertSame(10.5, $component->Length);
        $this->assertSame(5.2, $component->Width);
        $this->assertSame(42, $component->UnitId);
        $this->assertTrue($component->IsActive);
    }

    #[Test]
    public function it_casts_boolean_types(): void
    {
        $component = new TestMeasurementsWithCasts([
            'IsActive' => '1',
        ]);

        $this->assertTrue($component->IsActive);
        $this->assertIsBool($component->IsActive);

        $component->IsActive = '0';
        $this->assertFalse($component->IsActive);
    }

    #[Test]
    public function it_casts_float_types(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '123.456',
            'Width' => 789,
        ]);

        $this->assertSame(123.456, $component->Length);
        $this->assertIsFloat($component->Length);
        $this->assertSame(789.0, $component->Width);
        $this->assertIsFloat($component->Width);
    }

    #[Test]
    public function it_casts_integer_types(): void
    {
        $component = new TestMeasurementsWithCasts([
            'UnitId' => '999',
        ]);

        $this->assertSame(999, $component->UnitId);
        $this->assertIsInt($component->UnitId);
    }

    #[Test]
    public function it_casts_datetime_to_carbon(): void
    {
        $component = new TestMeasurementsWithCasts([
            'CreatedAt' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $component->CreatedAt);
        $this->assertEquals('2024-01-15', $component->CreatedAt->format('Y-m-d'));
        $this->assertEquals('10:30:00', $component->CreatedAt->format('H:i:s'));
    }

    #[Test]
    public function it_casts_datetime_with_custom_format_in_array(): void
    {
        $component = new TestMeasurementsWithCasts([
            'UpdatedAt' => '2024-03-20 14:25:30',
        ]);

        // Should return Carbon instance when accessed
        $this->assertInstanceOf(\Carbon\Carbon::class, $component->UpdatedAt);
        $this->assertEquals('2024-03-20', $component->UpdatedAt->format('Y-m-d'));
        $this->assertEquals('14:25', $component->UpdatedAt->format('H:i'));

        // Should serialize with custom format in toArray()
        $array = $component->toArray();
        $this->assertEquals('2024-03-20 14:25', $array['UpdatedAt']);
    }

    #[Test]
    public function it_casts_backed_enums(): void
    {
        $component = new TestMeasurementsWithCasts([
            'UnitType' => 'Metric',
            'Status' => 'Active',
        ]);

        $this->assertInstanceOf(TestUnitType::class, $component->UnitType);
        $this->assertSame(TestUnitType::METRIC, $component->UnitType);

        $this->assertInstanceOf(TestStatus::class, $component->Status);
        $this->assertSame(TestStatus::ACTIVE, $component->Status);

        // Test setting enum instance
        $component->Status = TestStatus::INACTIVE;
        $this->assertSame(TestStatus::INACTIVE, $component->Status);

        // Test serialization in toArray()
        $array = $component->toArray();
        $this->assertEquals('Metric', $array['UnitType']);
        $this->assertEquals('Inactive', $array['Status']);
    }

    #[Test]
    public function it_casts_nested_components(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'Dimension' => [
                'value' => '100.5',
                'unit' => 'Metric',
            ],
        ]);

        $this->assertInstanceOf(TestDimension::class, $component->Dimension);
        $this->assertSame(100.5, $component->Dimension->value);
        $this->assertIsFloat($component->Dimension->value);
        $this->assertInstanceOf(TestUnitType::class, $component->Dimension->unit);
        $this->assertSame(TestUnitType::METRIC, $component->Dimension->unit);
    }

    #[Test]
    public function it_allows_setting_nested_component_properties(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Dimension' => [
                'value' => '50.0',
            ],
        ]);

        $component->Dimension->value = '75.5';
        $component->Dimension->unit = TestUnitType::IMPERIAL;

        $this->assertSame(75.5, $component->Dimension->value);
        $this->assertSame(TestUnitType::IMPERIAL, $component->Dimension->unit);
    }

    #[Test]
    public function it_serializes_component_with_casts_to_array(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'Width' => '5.2',
            'UnitId' => '42',
            'IsActive' => '1',
            'UnitType' => 'Metric',
            'Status' => 'Active',
            'Metadata' => '{"key":"value"}',
            'CreatedAt' => '2024-01-15 10:30:00',
            'UpdatedAt' => '2024-03-20 14:25:30',
            'Dimension' => [
                'value' => '100.5',
                'unit' => 'Imperial',
            ],
        ]);

        $array = $component->toArray();

        // Primitives should be cast
        $this->assertSame(10.5, $array['Length']);
        $this->assertSame(5.2, $array['Width']);
        $this->assertSame(42, $array['UnitId']);
        $this->assertTrue($array['IsActive']);

        // Enums should be serialized as values
        $this->assertEquals('Metric', $array['UnitType']);
        $this->assertEquals('Active', $array['Status']);

        // DateTime should be ISO string
        $this->assertStringContainsString('2024-01-15', $array['CreatedAt']);

        // DateTime with custom format
        $this->assertEquals('2024-03-20 14:25', $array['UpdatedAt']);

        // Nested component should be array
        $this->assertIsArray($array['Dimension']);
        $this->assertSame(100.5, $array['Dimension']['value']);
        $this->assertEquals('Imperial', $array['Dimension']['unit']);
    }

    #[Test]
    public function it_handles_null_values(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => null,
            'UnitType' => null,
            'CreatedAt' => null,
        ]);

        $this->assertNull($component->Length);
        $this->assertNull($component->UnitType);
        $this->assertNull($component->CreatedAt);
    }

    #[Test]
    public function it_supports_array_access_with_casting(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'UnitId' => '42',
            'IsActive' => '1',
        ]);

        // Array access should also apply casting
        $this->assertSame(10.5, $component['Length']);
        $this->assertSame(42, $component['UnitId']);
        $this->assertTrue($component['IsActive']);

        // Setting via array access
        $component['Width'] = '5.2';
        $this->assertSame(5.2, $component->Width);
    }

    #[Test]
    public function it_handles_isset_with_null_casts(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'Width' => null,
        ]);

        $this->assertTrue(isset($component->Length));
        $this->assertFalse(isset($component->Width));
        $this->assertFalse(isset($component->NonExistent));
    }

    #[Test]
    public function it_allows_setting_entire_nested_component(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
        ]);

        $dimension = new TestDimension([
            'value' => '200.0',
            'unit' => 'Imperial',
        ]);

        $component->Dimension = $dimension;

        $this->assertInstanceOf(TestDimension::class, $component->Dimension);
        $this->assertSame(200.0, $component->Dimension->value);
        $this->assertSame(TestUnitType::IMPERIAL, $component->Dimension->unit);
    }

    #[Test]
    public function it_handles_unset_attributes(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'Width' => '5.2',
        ]);

        unset($component['Length']);

        $this->assertNull($component->Length);
        $this->assertSame(5.2, $component->Width);
    }

    #[Test]
    public function it_converts_to_json(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'UnitId' => '42',
            'UnitType' => 'Metric',
        ]);

        $json = $component->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame(10.5, $decoded['Length']);
        $this->assertSame(42, $decoded['UnitId']);
        $this->assertEquals('Metric', $decoded['UnitType']);
    }

    #[Test]
    public function it_json_serializes_correctly(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'UnitType' => 'Imperial',
        ]);

        $serialized = $component->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertSame(10.5, $serialized['Length']);
        $this->assertEquals('Imperial', $serialized['UnitType']);
    }

    #[Test]
    public function it_handles_empty_component_arrays_with_casts(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Dimension' => [],
        ]);

        $this->assertInstanceOf(TestDimension::class, $component->Dimension);
        $this->assertNull($component->Dimension->value);
        $this->assertNull($component->Dimension->unit);
    }

    #[Test]
    public function it_syncs_original_attributes(): void
    {
        $component = new TestMeasurementsWithCasts([
            'Length' => '10.5',
            'Width' => '5.2',
        ]);

        // Modify attributes
        $component->Length = '20.0';

        // Original should still be synced on construction
        $this->assertSame(20.0, $component->Length);
    }
}
