<?php

namespace Contoweb\AbacusApi\Models;

use ArrayAccess;
use Contoweb\AbacusApi\Models\Concerns\HasAttributes;
use Contoweb\AbacusApi\Models\Concerns\HasCasting;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

abstract class AbacusComponent implements Arrayable, ArrayAccess, JsonSerializable
{
    use HasAttributes,
        HasCasting;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->syncOriginal();
    }

    /**
     * Dynamically retrieve attributes on the component.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the component.
     *
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute exists on the component.
     *
     * @return bool
     */
    public function __isset(string $key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Determine if the given attribute exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        try {
            return ! is_null($this->getAttribute($offset));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the value for a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
