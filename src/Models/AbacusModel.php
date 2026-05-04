<?php

namespace Contoweb\AbacusApi\Models;

use ArrayAccess;
use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusODataQueryBuilder;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Models\Concerns\HasAttributes;
use Contoweb\AbacusApi\Models\Concerns\HasCasting;
use Contoweb\AbacusApi\OdataPaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use JsonSerializable;

abstract class AbacusModel implements Arrayable, ArrayAccess, JsonSerializable
{
    use HasAttributes,
        HasCasting;

    protected static string $resource;

    protected static string|array $primaryKey = 'Id';

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->syncOriginal();
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
     * Execute query and return all paginated results as Collection.
     *
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function paginate(?int $limit = null): OdataPaginator|BatchRequestItem
    {
        return static::query()->paginate($limit);
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
     * @param  array<string, mixed>  $data
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
     */
    public static function delete(int|string|array $idOrCriteria): ?BatchRequestItem
    {
        return static::query()->delete($idOrCriteria);
    }

    /**
     * Update entity by ID.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @param  array<string, mixed>  $data  Data to update
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function update(int|string|array $idOrCriteria, array $data): static|BatchRequestItem
    {
        return static::query()->update($idOrCriteria, $data);
    }

    /**
     * Execute query and return first result
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function first(): AbacusModel|BatchRequestItem|null
    {
        return static::query()->first();
    }

    /**
     * Disable UUID escaping so UUID values are formatted without string quotes in OData queries.
     *
     * When disabled (default), UUID values are output as raw UUID: `$filter=Id eq 57bc1fe4-bac4-6549-53fa-8ce85e63f4cb`
     */
    public static function withoutUuidEscaping(): AbacusODataQueryBuilder
    {
        return static::query()->withoutUuidEscaping();
    }

    /**
     * Enable UUID escaping so UUID values are treated as regular strings in OData queries.
     *
     * When enabled, UUID values are wrapped in single quotes: `$filter=Id eq '57bc1fe4-bac4-6549-53fa-8ce85e63f4cb'`
     */
    public static function withUuidEscaping(): AbacusODataQueryBuilder
    {
        return static::query()->withUuidEscaping();
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @return void
     */
    public function __set(string $key, mixed $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
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
