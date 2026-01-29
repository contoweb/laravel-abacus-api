<?php

namespace Contoweb\AbacusApi\Models;

use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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
     * Set the maximum number of pages to retrieve when cursor pagination is enabled
     *
     * @param int $limit
     * @return AbacusQueryBuilder
     */
    public static function pages(int $limit): AbacusQueryBuilder
    {
        return static::query()->pages($limit);
    }

    /**
     * Enable automatic pagination through OData nextLink
     *
     * @return AbacusQueryBuilder
     */
    public static function cursor(): AbacusQueryBuilder
    {
        return static::query()->cursor();
    }

    /**
     * Enable automatic pagination with a callback for each page
     *
     * @param callable $callback Callback function receiving (Collection $items, int $pageNumber)
     * @return AbacusQueryBuilder
     */
    public static function cursorWithCallback(callable $callback): AbacusQueryBuilder
    {
        return static::query()->cursorWithCallback($callback);
    }

    /**
     * Execute query and return all paginated results as Collection
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
     * Execute query and return all paginated results as Collection
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public static function get(): Collection
    {
        return static::query()->get();
    }

    /**
     * Prepare a get operation as batch request item
     *
     * @return BatchRequestItem
     */
    public static function getAsBatch(): BatchRequestItem
    {
        return static::query()->getAsBatch();
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

        return $result;
    }

    /**
     *  Prepare a find operation as batch request item
     *
     * @param int|array $id
     * @return BatchRequestItem
     */
    public static function findAsBatch(int|array $id): BatchRequestItem
    {
        return static::query()->findAsBatch($id);
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
        return static::query()->create($attributes);
    }

    /**
     * Prepare a create operation as batch request item
     *
     * @param array $data
     * @return BatchRequestItem
     */
    public static function createAsBatch(array $data): BatchRequestItem
    {
        return static::query()->createAsBatch($data);
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
        static::query()->delete($id);
    }

    /**
     * Prepare a delete operation as batch request item
     *
     * @param int|array $id
     * @return BatchRequestItem
     */
    public static function deleteAsBatch(int|array $id): BatchRequestItem
    {
        return static::query()->deleteAsBatch($id);
    }

    /**
     * Update entity by ID
     *
     * @param int|array $id Single value for simple keys, array for composite keys
     * @param array $data Data to update
     * @return static
     * @throws ConnectionException
     * @throws RequestException
     * @example Simple: Customers::update(210, ['Name' => 'Test'])
     * @example Composite: StockBatches::update(['BatchNumber' => '123', ...], ['Remark' => 'Test'])
     */
    public static function update(int|array $id, array $data): static
    {
        return static::query()->update($id, $data);
    }

    /**
     * Prepare a update operation as batch request item
     *
     * @param int|array $id
     * @param array $data
     * @return BatchRequestItem
     */
    public static function updateAsBatch(int|array $id, array $data): BatchRequestItem
    {
        return static::query()->updateAsBatch($id, $data);
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

    /**
     * Get the primary key(s) for the model
     *
     * @return string|array Single key name or array of key names for composite keys
     */
    public static function getPrimaryKey(): string|array
    {
        return static::$primaryKey;
    }

    /**
     * Determine if the model has a single primary key
     *
     * @return bool
     */
    public static function hasSinglePrimaryKey(): bool
    {
        return is_string(static::$primaryKey);
    }

    /**
     * Determine if the model has a composite primary key
     *
     * @return bool
     */
    public static function hasCompositePrimaryKey(): bool
    {
        return is_array(static::$primaryKey);
    }
}
