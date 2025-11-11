<?php

namespace Contoweb\AbacusApi\Models;

use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\Enums\ODataOperator;

abstract class AbacusModel
{
    protected static string $resource;
    protected array         $attributes = [];
    protected array         $original   = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original   = $attributes;
    }

    /**
     * Create query builder
     */
    public static function query(): AbacusQueryBuilder
    {
        $service = app(AbacusService::class);

        return new AbacusQueryBuilder($service, static::$resource, static::class);
    }

    /**
     *  Fetch all entities across all pagination pages as Collection
     *  Follows all @odata.nextLink URLs automatically
     */
    public static function all()
    {
        return static::query()->getAllPages()->map(fn($item) => new static($item));
    }

    /**
     * Fetch all entities (first page only) as Collection
     */
    public static function firstPage()
    {
        return static::query()->get()->map(fn($item) => new static($item));
    }

    /**
     * Find entity via primary key
     */
    public static function find(mixed $id): ?static
    {
        $result = static::query()->find($id);

        return $result ? new static($result) : null;
    }

    /**
     * Start where query
     * Example: Project::where('Id', ODataOperator::EQUALS, 9100)->get()
     * Example: Project::where('Id', 'eq', 9100)->get()
     */
    public static function where(string $field, ODataOperator | string $operator, mixed $value): AbacusQueryBuilder
    {
        return static::query()->where($field, $operator, $value);
    }

    /**
     * Start select query
     */
    public static function select(array | string $fields): AbacusQueryBuilder
    {
        return static::query()->select($fields);
    }

    /**
     * Top N Entities
     */
    public static function top(int $limit): AbacusQueryBuilder
    {
        return static::query()->top($limit);
    }

    /**
     * OrderBy-Query starten
     */
    public static function orderBy(string $field, string $direction = 'asc'): AbacusQueryBuilder
    {
        return static::query()->orderBy($field, $direction);
    }

    /**
     * Expand Navigation Properties
     */
    public static function expand(array | string $relations): AbacusQueryBuilder
    {
        return static::query()->expand($relations);
    }

    /**
     * Create entity
     */
    public static function create(array $attributes): static
    {
        $service = app(AbacusService::class);
        $result  = $service->create(static::$resource, $attributes);

        return new static($result);
    }

    /**
     * Save entity (create or update)
     */
    public function save(): static
    {
        $service = app(AbacusService::class);

        if (isset($this->attributes['Id'])) {
            $result           = $service->update(static::$resource, $this->attributes['Id'], $this->getDirty());
            $this->attributes = array_merge($this->attributes, $result);
        } else {
            $result           = $service->create(static::$resource, $this->attributes);
            $this->attributes = $result;
        }

        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Update entity
     */
    public function update(array $attributes = []): static
    {
        if ( ! empty($attributes)) {
            foreach ($attributes as $key => $value) {
                $this->setAttribute($key, $value);
            }
        }

        return $this->save();
    }

    /**
     * Delete entity
     */
    public function delete(): bool
    {
        if ( ! isset($this->attributes['Id'])) {
            return false;
        }

        $service = app(AbacusService::class);

        return $service->delete(static::$resource, $this->attributes['Id']);
    }

    /**
     * Get attribute
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set attribute
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return model as array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Return model as JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->attributes, $options);
    }

    /**
     * Check if attributes have changed
     */
    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return ($this->attributes[$key] ?? null) !== ($this->original[$key] ?? null);
        }

        return $this->attributes !== $this->original;
    }

    /**
     * Get changed attributes
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->isDirty($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Restore original attributes
     */
    public function fresh(): ?static
    {
        if ( ! isset($this->attributes['Id'])) {
            return null;
        }

        return static::find($this->attributes['Id']);
    }

    /**
     * Magic getter
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    /**
     * Magic setter
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Magic isset
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Get resource name
     */
    public static function getResource(): string
    {
        return static::$resource;
    }
}