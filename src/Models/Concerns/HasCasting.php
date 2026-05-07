<?php

namespace Contoweb\AbacusApi\Models\Concerns;

use BackedEnum;
use Carbon\Carbon;
use Contoweb\AbacusApi\Models\AbacusComponent;
use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Support\Collection;

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
                if (is_a($castType, BackedEnum::class, true)) {
                    return $this->asEnum($value, $castType);
                }

                // Check if it's an AbacusComponent
                if (class_exists($castType) && is_subclass_of($castType, AbacusComponent::class)) {
                    return $this->asInstance($key, $value, $castType, AbacusComponent::class);
                }

                // Check if it's an AbacusModel
                if (class_exists($castType) && is_subclass_of($castType, AbacusModel::class)) {
                    return $this->asInstance($key, $value, $castType, AbacusModel::class);
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
     *
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enumClass
     */
    protected function asEnum(mixed $value, string $enumClass): ?BackedEnum
    {
        if ($value instanceof BackedEnum) {
            return $value;
        }

        return $enumClass::tryFrom($value);
    }

    /**
     * Cast the given value to an AbacusComponent, AbacusModel or Collection.
     */
    protected function asInstance(string $key, mixed $value, string $instanceClass, string $baseClass): AbacusModel|AbacusComponent|Collection
    {
        if ($value instanceof $baseClass) {
            return $value;
        }

        if ($value instanceof Collection) {
            return $value;
        }

        // Collection (array of arrays)
        if (is_array($value) && ! empty($value) && array_is_list($value)) {
            $class = collect($value)->map(fn ($item) => new $instanceClass($item));
            $this->attributes[$key] = $class;

            return $class;
        }

        // Create an AbacusModel or AbacusComponent and store it in attributes to persist modifications
        $class = new $instanceClass(is_array($value) ? $value : []);
        $this->attributes[$key] = $class;

        return $class;
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
            // Handle AbacusComponent, AbacusModel or Collection serialization
            elseif ($value instanceof AbacusComponent || $value instanceof AbacusModel || $value instanceof Collection) {
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
