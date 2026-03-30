<?php

namespace Contoweb\AbacusApi\Models\Concerns;

use BackedEnum;
use Carbon\Carbon;
use Contoweb\AbacusApi\Models\AbacusComponent;

trait HasCasting
{
    /**
     * Determine whether an attribute should be cast.
     */
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Get the type of cast for a model attribute.
     */
    protected function getCastType(string $key): string
    {
        $cast = $this->casts[$key] ?? null;

        if (is_null($cast)) {
            return 'string';
        }

        // Handle custom format (e.g., datetime:Y-m-d)
        if (str_contains($cast, ':')) {
            return explode(':', $cast, 2)[0];
        }

        return $cast;
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $castType = $this->getCastType($key);

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return (object) $value;
            case 'date':
            case 'datetime':
            case 'timestamp':
                return $this->asDateTime($value);
            default:
                // Check if it's a backed enum
                if (enum_exists($castType)) {
                    return $this->asEnum($value, $castType);
                }

                // Check if it's an AbacusComponent
                if (class_exists($castType) && is_subclass_of($castType, AbacusComponent::class)) {
                    return $this->asComponent($key, $value, $castType);
                }

                return $value;
        }
    }

    /**
     * Cast the given value to a Carbon instance.
     */
    protected function asDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return Carbon::parse($value);
    }

    /**
     * Cast the given value to a backed enum.
     */
    protected function asEnum(mixed $value, string $enumClass): ?BackedEnum
    {
        if ($value instanceof BackedEnum) {
            return $value;
        }

        return $enumClass::from($value);
    }

    /**
     * Cast the given value to an AbacusComponent.
     */
    protected function asComponent(string $key, mixed $value, string $componentClass): AbacusComponent
    {
        if ($value instanceof AbacusComponent) {
            return $value;
        }

        // Ensure value is an array for the component constructor
        if (! is_array($value)) {
            $value = [];
        }

        // Create component and store it in attributes so modifications persist
        $component = new $componentClass($value);
        $this->attributes[$key] = $component;

        return $component;
    }

    /**
     * Convert the model's attributes to an array.
     */
    protected function attributesToArray(): array
    {
        $attributes = $this->attributes;

        // Apply casts during serialization
        foreach ($this->casts as $key => $cast) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            // Get the cast value (important for datetime/enum/component casting)
            $value = $this->getAttributeValue($key);

            // Handle datetime with custom format
            if ($value instanceof Carbon && str_contains($cast, ':')) {
                [, $format] = explode(':', $cast, 2);
                $attributes[$key] = $value->format($format);
            }
            // Handle enum serialization
            elseif ($value instanceof BackedEnum) {
                $attributes[$key] = $value->value;
            }
            // Handle Carbon instances with default format
            elseif ($value instanceof Carbon) {
                $attributes[$key] = $value->toISOString();
            }
            // Handle AbacusComponent serialization
            elseif ($value instanceof AbacusComponent) {
                $attributes[$key] = $value->toArray();
            }
            // For all other casts (primitives like int, float, bool), use the cast value
            else {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model instance to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
