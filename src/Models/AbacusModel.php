<?php

namespace Contoweb\AbacusApi\Models;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusODataQueryBuilder;
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
    protected array $original = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
    }

    /**
     * Create query builder
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function query(): AbacusODataQueryBuilder
    {
        $client = app(AbacusODataClient::class);

        return new AbacusODataQueryBuilder($client, static::$resource, static::class);
    }

    /**
     * Set the maximum number of pages to retrieve when cursor pagination is enabled
     *
     * @param int $limit
     * @return AbacusODataQueryBuilder
     */
    public static function pages(int $limit): AbacusODataQueryBuilder
    {
        return static::query()->pages($limit);
    }

    /**
     * Enable automatic pagination through OData nextLink
     *
     * @return AbacusODataQueryBuilder
     */
    public static function cursor(): AbacusODataQueryBuilder
    {
        return static::query()->cursor();
    }

    /**
     * Enable automatic pagination with a callback for each page
     *
     * @param callable $callback Callback function receiving (Collection $items, int $pageNumber)
     * @return AbacusODataQueryBuilder
     */
    public static function cursorWithCallback(callable $callback): AbacusODataQueryBuilder
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
     * @return Collection<static>
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
     * @param int|string|array<string, int|string> $idOrCriteria Single value for simple keys, array for composite keys
     * @return AbacusModel
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function find(int|string|array $idOrCriteria): static
    {
        return static::query()->find($idOrCriteria);
    }

    /**
     *  Prepare a find operation as batch request item
     *
     * @param int|string|array<string, int|string> $idOrCriteria
     * @return BatchRequestItem
     */
    public static function findAsBatch(int|string|array $idOrCriteria): BatchRequestItem
    {
        return static::query()->findAsBatch($idOrCriteria);
    }

    /**
     * Start where query
     * Example: Project::where('Id', 'eq', 9100)->get()
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function where(string $field, ODataOperator|string $operator, mixed $value): AbacusODataQueryBuilder
    {
        return static::query()->where($field, $operator, $value);
    }

    /**
     * Start select query
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function select(array|string $fields): AbacusODataQueryBuilder
    {
        return static::query()->select($fields);
    }

    /**
     * Top N Entities
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function top(int $limit): AbacusODataQueryBuilder
    {
        return static::query()->top($limit);
    }

    /**
     * OrderBy-Query starten
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function orderBy(string $field, string $direction = 'asc'): AbacusODataQueryBuilder
    {
        return static::query()->orderBy($field, $direction);
    }

    /**
     * Expand Navigation Properties
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function expand(array|string $relations): AbacusODataQueryBuilder
    {
        return static::query()->expand($relations);
    }

    /**
     * Create entity
     *
     * @param array<string, int|string> $data
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function create(array $data): static
    {
        return static::query()->create($data);
    }

    /**
     * Prepare a create operation as batch request item
     *
     * @param array<string, int|string> $data
     * @return BatchRequestItem
     */
    public static function createAsBatch(array $data): BatchRequestItem
    {
        return static::query()->createAsBatch($data);
    }

    /**
     * Delete entity by ID
     *
     * @param int|string|array<string, int|string> $idOrCriteria Single value for simple keys, array for composite keys
     * @return void
     * @throws ConnectionException
     * @throws RequestException
     * @example Single key: Customers::delete(210)
     * @example Composite key: StockBatches::delete(['BatchNumber' => '123', 'ProductId' => 456])
     */
    public static function delete(int|string|array $idOrCriteria): void
    {
        static::query()->delete($idOrCriteria);
    }

    /**
     * Prepare a delete operation as batch request item
     *
     * @param int|string|array<string, int|string> $idOrCriteria
     * @return BatchRequestItem
     */
    public static function deleteAsBatch(int|string|array $idOrCriteria): BatchRequestItem
    {
        return static::query()->deleteAsBatch($idOrCriteria);
    }

    /**
     * Update entity by ID
     *
     * @param int|string|array<string, int|array> $idOrCriteria Single value for simple keys, array for composite keys
     * @param array<string, int|string> $data Data to update
     * @return static
     * @throws ConnectionException
     * @throws RequestException
     * @example Simple: Customers::update(210, ['Name' => 'Test'])
     * @example Composite: StockBatches::update(['BatchNumber' => '123', ...], ['Remark' => 'Test'])
     */
    public static function update(int|string|array $idOrCriteria, array $data): static
    {
        return static::query()->update($idOrCriteria, $data);
    }

    /**
     * Prepare a update operation as batch request item
     *
     * @param int|string|array<string|string, int|string> $idOrCriteria
     * @param array<string, int|string> $data
     * @return BatchRequestItem
     */
    public static function updateAsBatch(int|string|array $idOrCriteria, array $data): BatchRequestItem
    {
        return static::query()->updateAsBatch($idOrCriteria, $data);
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
