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
     * Create query builder.
     */
    public static function query(): AbacusODataQueryBuilder
    {
        $client = app(AbacusODataClient::class);

        return new AbacusODataQueryBuilder($client, static::$resource, static::class);
    }

    /**
     * Set the maximum number of pages to retrieve when cursor pagination is enabled.
     */
    public static function pages(int $limit): AbacusODataQueryBuilder
    {
        return static::query()->pages($limit);
    }

    /**
     * Enable automatic pagination through OData nextLink.
     */
    public static function cursor(): AbacusODataQueryBuilder
    {
        return static::query()->cursor();
    }

    /**
     * Enable automatic pagination with a callback for each page.
     *
     * @param  callable  $callback  Callback function receiving (Collection $items, int $pageNumber)
     */
    public static function cursorWithCallback(callable $callback): AbacusODataQueryBuilder
    {
        return static::query()->cursorWithCallback($callback);
    }

    /**
     * Execute query and return all paginated results as Collection.
     *
     * @return Collection<int, static>|BatchRequestItem
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function all(): Collection|BatchRequestItem
    {
        return static::query()->get();
    }

    /**
     * Execute query and return all paginated results as Collection.
     *
     * @return Collection<static>|BatchRequestItem
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public static function get(): Collection|BatchRequestItem
    {
        return static::query()->get();
    }

    /**
     * Find entity via primary key.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function find(int|string|array $idOrCriteria): static|BatchRequestItem
    {
        return static::query()->find($idOrCriteria);
    }

    /**
     * Start where query.
     * Example: Project::where('Id', 'eq', 9100)->get()
     */
    public static function where(string $field, ODataOperator|string $operator, mixed $value): AbacusODataQueryBuilder
    {
        return static::query()->where($field, $operator, $value);
    }

    /**
     * Start select query.
     */
    public static function select(array|string $fields): AbacusODataQueryBuilder
    {
        return static::query()->select($fields);
    }

    /**
     * Top N Entities.
     */
    public static function top(int $limit): AbacusODataQueryBuilder
    {
        return static::query()->top($limit);
    }

    /**
     * OrderBy-Query starten.
     */
    public static function orderBy(string $field, string $direction = 'asc'): AbacusODataQueryBuilder
    {
        return static::query()->orderBy($field, $direction);
    }

    /**
     * Expand Navigation Properties.
     */
    public static function expand(array|string $relations): AbacusODataQueryBuilder
    {
        return static::query()->expand($relations);
    }

    /**
     * Create entity.
     *
     * @param  array<string, int|string>  $data
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function create(array $data): static|BatchRequestItem
    {
        return static::query()->create($data);
    }

    /**
     * Delete entity by ID.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     *
     * @throws ConnectionException
     * @throws RequestException
     *
     * @example Single key: Customers::delete(210)
     * @example Composite key: StockBatches::delete(['BatchNumber' => '123', 'ProductId' => 456])
     */
    public static function delete(int|string|array $idOrCriteria): ?BatchRequestItem
    {
        return static::query()->delete($idOrCriteria);
    }

    /**
     * Update entity by ID.
     *
     * @param  int|string|array<string, int|array>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @param  array<string, int|string>  $data  Data to update
     *
     * @throws ConnectionException
     * @throws RequestException
     *
     * @example Simple: Customers::update(210, ['Name' => 'Test'])
     * @example Composite: StockBatches::update(['BatchNumber' => '123', ...], ['Remark' => 'Test'])
     */
    public static function update(int|string|array $idOrCriteria, array $data): static|BatchRequestItem
    {
        return static::query()->update($idOrCriteria, $data);
    }

    /**
     * Get attribute.
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set attribute.
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get all attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return model as array.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Magic getter.
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    /**
     * Magic setter.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Magic isset.
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Get resource name.
     */
    public static function getResource(): string
    {
        return static::$resource;
    }

    /**
     * Get the primary key(s) for the model.
     *
     * @return string|array Single key name or array of key names for composite keys
     */
    public static function getPrimaryKey(): string|array
    {
        return static::$primaryKey;
    }

    /**
     * Determine if the model has a single primary key.
     */
    public static function hasSinglePrimaryKey(): bool
    {
        return is_string(static::$primaryKey);
    }

    /**
     * Determine if the model has a composite primary key.
     */
    public static function hasCompositePrimaryKey(): bool
    {
        return is_array(static::$primaryKey);
    }
}
