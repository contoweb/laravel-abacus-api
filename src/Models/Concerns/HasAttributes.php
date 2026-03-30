<?php

namespace Contoweb\AbacusApi\Models\Concerns;

use BackedEnum;

trait HasAttributes
{
    /**
     * The model's attributes.
     */
    protected array $attributes = [];

    /**
     * The model's original attributes.
     */
    protected array $original = [];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [];

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key): mixed
    {
        if (! $key) {
            return null;
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttributeValue($key);
        }

        return null;
    }

    /**
     * Get a plain attribute value (without casting).
     */
    protected function getAttributeFromArray(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get an attribute value with casting applied.
     */
    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->getAttributeFromArray($key);

        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Set a given attribute on the model.
     *
     * @return $this
     */
    public function setAttribute(string $key, mixed $value): static
    {
        // Handle enum casting on set
        if ($this->hasCast($key)) {
            $castType = $this->getCastType($key);

            // If value is already the enum instance, convert to stored value
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }
            // For array/json casts, encode if needed
            elseif (in_array($castType, ['array', 'json', 'object']) && is_array($value)) {
                $value = json_encode($value);
            }
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get all attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
