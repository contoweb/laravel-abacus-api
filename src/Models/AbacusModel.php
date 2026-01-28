<?php

namespace Contoweb\AbacusApi\Models;

use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

abstract class AbacusModel
{
    protected static string $resource;
    protected array $attributes = [];
    protected array $original   = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original   = $attributes;
    }

    /**
     * Create query builder
     *
     * @return AbacusQueryBuilder<static>
     */
    public static function query(): AbacusQueryBuilder
    {
        $client = app(AbacusClient::class);

        return new AbacusQueryBuilder($client, static::$resource, static::class);
    }

    /**
     *  Fetch all entities across all pagination pages as Collection
     *
     * @return Collection<int, static>
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Find entity via primary key
     *
     * @param int|array $id Single value for simple keys, array for composite keys
     * @return AbacusModel|null
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function find(int|array $id): ?static
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
     * Create entity
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function create(array $attributes): static
    {
        $result = static::query()->create($attributes);

        if ($result instanceof static) {
            return $result;
        }

        return new static($result);
    }

    /**
     * Delete entity by ID
     *
     * @param int|array $id Single value for simple keys, array for composite keys
     * @return void
     * @throws ConnectionException
     * @throws RequestException
     * @example Single key: Customers::delete(210)
     * @example Composite key: StockBatches::delete(['BatchNumber' => '123', 'ProductId' => 456])
     */
    public static function delete(int|array $id): void
    {
        static::query()->id($id)->delete();
    }

    /**
     * Update entity by ID
     * Supports chaining with select() and expand()
     *
     * @param int|array $id Single value for simple keys, array for composite keys
     * @param array $data Data to update
     * @return static
     * @throws ConnectionException
     * @throws RequestException
     * @example Chained: Customers::select(['Id', 'Name'])->update(210, ['Name' => 'Test'])
     *
     * @example Simple: Customers::update(210, ['Name' => 'Test'])
     * @example Composite: StockBatches::update(['BatchNumber' => '123', ...], ['Remark' => 'Test'])
     */
    public static function update(int|array $id, array $data): static
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
