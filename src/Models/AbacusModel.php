<?php

namespace Contoweb\AbacusApi\Models;

use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Illuminate\Support\Collection;

abstract class AbacusModel
{
    protected static string $resource;
    protected static string|array $primaryKey = 'Id';
    protected array $attributes = [];
    protected array $original   = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original   = $attributes;
    }

    /**
     * Get the primary key field(s) for this model
     *
     * @return string|array
     */
    public static function getPrimaryKey(): string|array
    {
        return static::$primaryKey;
    }

    /**
     * Check if this model has a single primary key
     */
    public static function hasSinglePrimaryKey(): bool
    {
        return is_string(static::$primaryKey);
    }

    /**
     * Check if this model has a composite primary key
     */
    public static function hasCompositePrimaryKey(): bool
    {
        return is_array(static::$primaryKey);
    }

    /**
     * Create query builder
     *
     * @return AbacusQueryBuilder<static>
     */
    public static function query(): AbacusQueryBuilder
    {
        $service = app(AbacusService::class);

        return new AbacusQueryBuilder($service, static::$resource, static::class);
    }

    /**
     *  Fetch all entities across all pagination pages as Collection
     *  Follows all @odata.nextLink URLs automatically
     *
     *  @return Collection<int, static>
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Fetch all entities (first page only) as Collection
     *
     * @return Collection<int, static>
     */
    public static function firstPage(): Collection
    {
        return static::query()->getFirstPage();
    }

    /**
     * Find entity via primary key
     *
     * @param mixed $id Single value for simple keys, array for composite keys
     */
    public static function find(mixed $id): ?static
    {
        $result = static::query()->find($id);

        if ($result === null) {
            return null;
        }

        if (is_array($result)) {
            return new static($result);
        }

        return $result;
    }

    /**
     * Start where query
     * Example: Project::where('Id', ODataOperator::EQUALS, 9100)->get()
     * Example: Project::where('Id', 'eq', 9100)->get()
     *
     * @return AbacusQueryBuilder<static>
     */
    public static function where(string $field, ODataOperator | string $operator, mixed $value): AbacusQueryBuilder
    {
        return static::query()->where($field, $operator, $value);
    }

    /**
     * Start select query
     *
     * @return AbacusQueryBuilder<static>
     */
    public static function select(array | string $fields): AbacusQueryBuilder
    {
        return static::query()->select($fields);
    }

    /**
     * Top N Entities
     *
     * @return AbacusQueryBuilder<static>
     */
    public static function top(int $limit): AbacusQueryBuilder
    {
        return static::query()->top($limit);
    }

    /**
     * OrderBy-Query starten
     *
     * @return AbacusQueryBuilder<static>
     */
    public static function orderBy(string $field, string $direction = 'asc'): AbacusQueryBuilder
    {
        return static::query()->orderBy($field, $direction);
    }

    /**
     * Expand Navigation Properties
     *
     * @return AbacusQueryBuilder<static>
     */
    public static function expand(array | string $relations): AbacusQueryBuilder
    {
        return static::query()->expand($relations);
    }

    /**
     * Create entity (supports batch mode)
     */
    public static function create(array $attributes): static
    {
        $result = static::query()->post($attributes);

        if ($result instanceof static) {
            return $result;
        }

        return new static($result);
    }

    /**
     * Delete entity by ID (supports batch mode)
     *
     * @example Single key: Customers::delete(210)
     * @example Composite key: StockBatches::delete(['BatchNumber' => '123', 'ProductId' => 456])
     *
     * @param mixed $id Single value for simple keys, array for composite keys
     * @return bool
     */
    public static function delete(mixed $id): bool
    {
        return static::query()->id($id)->delete();
    }

    /**
     * Update entity by ID (supports batch mode)
     * Supports chaining with select() and expand()
     *
     * @example Simple: Customers::update(210, ['Name' => 'Test'])
     * @example Composite: StockBatches::update(['BatchNumber' => '123', ...], ['Remark' => 'Test'])
     * @example Chained: Customers::select(['Id', 'Name'])->update(210, ['Name' => 'Test'])
     *
     * @param mixed $id Single value for simple keys, array for composite keys
     * @param array $data Data to update
     * @return static
     */
    public static function update(mixed $id, array $data): static
    {
        $result = static::query()->update($id, $data);

        if ($result instanceof static) {
            return $result;
        }

        return new static($result);
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
