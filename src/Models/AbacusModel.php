<?php

namespace Contoweb\AbacusApi\Models;

use ArrayAccess;
use Closure;
use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusODataQueryBuilder;
use Contoweb\AbacusApi\Attributes\Scope;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Exceptions\AbacusAuthenticationException;
use Contoweb\AbacusApi\Exceptions\AbacusRateLimitException;
use Contoweb\AbacusApi\Models\Concerns\HasAttributes;
use Contoweb\AbacusApi\Models\Concerns\HasCasting;
use Contoweb\AbacusApi\ODataPaginator;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Traits\ForwardsCalls;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionMethod;

abstract class AbacusModel implements Arrayable, ArrayAccess, JsonSerializable
{
    use ForwardsCalls,
        HasAttributes,
        HasCasting;

    protected static string $resource;

    protected static string|array $primaryKey = 'Id';

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->syncOriginal();
    }

    /**
     * Create a query builder for the model.
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function query(): AbacusODataQueryBuilder
    {
        $client = app(AbacusODataClient::class);

        return new AbacusODataQueryBuilder($client, static::$resource, static::class);
    }

    /**
     * Executes the query and returns a paginated result, or a BatchRequestItem in a batch context.
     *
     * The $perPage parameter controls the OData query option $top to set the number of items per page.
     *
     * @param  int|null  $perPage  Number of items per page
     * @return ODataPaginator<static>|BatchRequestItem
     *
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws RequestException
     */
    public static function paginate(?int $perPage = null): ODataPaginator|BatchRequestItem
    {
        return static::query()->paginate($perPage);
    }

    /**
     * Find an entity by its primary key, or return a BatchRequestItem in a batch context.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function find(int|string|array $idOrCriteria): static|BatchRequestItem
    {
        return static::query()->find($idOrCriteria);
    }

    /**
     * Filter with OData operators or Laravel operators (=, >, >=, <, <=).
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function where(string $field, ODataOperator|string $operator, mixed $value): AbacusODataQueryBuilder
    {
        return static::query()->where($field, $operator, $value);
    }

    /**
     * Select only specific properties ($select).
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function select(array|string $fields): AbacusODataQueryBuilder
    {
        return static::query()->select($fields);
    }

    /**
     * Limit the number of results ($top).
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function top(int $limit): AbacusODataQueryBuilder
    {
        return static::query()->top($limit);
    }

    /**
     * Sort by a property, asc or desc ($orderby).
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function orderBy(string $field, string $direction = 'asc'): AbacusODataQueryBuilder
    {
        return static::query()->orderBy($field, $direction);
    }

    /**
     * Expand navigation properties ($expand).
     *
     * Pass a closure as second argument to set the query options of the expanded relation.
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function expand(array|string $relations, string|Closure|null $callback = null): AbacusODataQueryBuilder
    {
        return static::query()->expand(...func_get_args());
    }

    /**
     * Create an entity, or return a BatchRequestItem in a batch context.
     *
     * @param  array<string, mixed>  $data  Attributes of the new entity
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function create(array $data): static|BatchRequestItem
    {
        return static::query()->create($data);
    }

    /**
     * Delete an entity by its primary key, or return a BatchRequestItem in a batch context.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function delete(int|string|array $idOrCriteria): ?BatchRequestItem
    {
        return static::query()->delete($idOrCriteria);
    }

    /**
     * Update an entity by its primary key, or return a BatchRequestItem in a batch context.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @param  array<string, mixed>  $data  Attributes to update
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function update(int|string|array $idOrCriteria, array $data): static|BatchRequestItem
    {
        return static::query()->update($idOrCriteria, $data);
    }

    /**
     * Apply the callback if the given "value" is (or resolves to) truthy.
     *
     * @template TWhenParameter
     * @template TWhenReturnType
     *
     * @param  (Closure(AbacusODataQueryBuilder<static>): TWhenParameter)|TWhenParameter|null  $value
     * @param  (callable(AbacusODataQueryBuilder<static>, TWhenParameter): TWhenReturnType)|null  $callback
     * @param  (callable(AbacusODataQueryBuilder<static>, TWhenParameter): TWhenReturnType)|null  $default
     * @return AbacusODataQueryBuilder<static>|TWhenReturnType
     */
    public static function when(mixed $value = null, ?callable $callback = null, ?callable $default = null): mixed
    {
        return static::query()->when(...func_get_args());
    }

    /**
     * Apply the callback if the given "value" is (or resolves to) falsy.
     *
     * @template TUnlessParameter
     * @template TUnlessReturnType
     *
     * @param  (Closure(AbacusODataQueryBuilder<static>): TUnlessParameter)|TUnlessParameter|null  $value
     * @param  (callable(AbacusODataQueryBuilder<static>, TUnlessParameter): TUnlessReturnType)|null  $callback
     * @param  (callable(AbacusODataQueryBuilder<static>, TUnlessParameter): TUnlessReturnType)|null  $default
     * @return AbacusODataQueryBuilder<static>|TUnlessReturnType
     */
    public static function unless(mixed $value = null, ?callable $callback = null, ?callable $default = null): mixed
    {
        return static::query()->unless(...func_get_args());
    }

    /**
     * Execute a bound OData action on an entity.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @param  string  $actionName  Fully qualified action name (e.g. ch.abacus.orde.TriggerSalesOrderNextStep)
     * @param  array<string, mixed>  $data  Action parameters
     * @param  AbacusModel|AbacusComponent|null  $returnType  Type of the result
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function action(
        int|string|array $idOrCriteria,
        string $actionName,
        array $data = [],
        AbacusModel|AbacusComponent|null $returnType = null
    ): mixed {
        return static::query()->action($idOrCriteria, $actionName, $data, $returnType);
    }

    /**
     * Execute the query and return the first result, or a BatchRequestItem in a batch context.
     *
     * @return static|BatchRequestItem|null
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function first(): AbacusModel|BatchRequestItem|null
    {
        return static::query()->first();
    }

    /**
     * Fetch a single regular property of an entity.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @param  string  $property  Name of the property
     * @return array|mixed
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function findProperty(int|string|array $idOrCriteria, string $property): mixed
    {
        return static::query()->findProperty($idOrCriteria, $property);
    }

    /**
     * Fetch the raw content of a stream property.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @param  string  $property  Name of the stream property
     * @return string The raw response body
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function stream(int|string|array $idOrCriteria, string $property): string
    {
        return static::query()->stream($idOrCriteria, $property);
    }

    /**
     * Fetch the file of the Content property.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @return string The raw file content
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function content(int|string|array $idOrCriteria): string
    {
        return static::query()->content($idOrCriteria);
    }

    /**
     * Fetch the file of the FileStream property.
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @return string The raw file content
     *
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     * @throws ConnectionException
     * @throws RequestException
     */
    public static function fileStream(int|string|array $idOrCriteria): string
    {
        return static::query()->fileStream($idOrCriteria);
    }

    /**
     * Disable UUID escaping so UUID values are formatted without string quotes in OData queries.
     *
     * When disabled (default), UUID values are output as raw UUID: `$filter=Id eq 57bc1fe4-bac4-6549-53fa-8ce85e63f4cb`
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function withoutUuidEscaping(): AbacusODataQueryBuilder
    {
        return static::query()->withoutUuidEscaping();
    }

    /**
     * Enable UUID escaping so UUID values are treated as regular strings in OData queries.
     *
     * When enabled, UUID values are wrapped in single quotes: `$filter=Id eq '57bc1fe4-bac4-6549-53fa-8ce85e63f4cb'`
     *
     * @return AbacusODataQueryBuilder<static>
     */
    public static function withUuidEscaping(): AbacusODataQueryBuilder
    {
        return static::query()->withUuidEscaping();
    }

    /**
     * Get the model class a navigation property is cast to.
     *
     * @return class-string<AbacusModel>
     *
     * @throws InvalidArgumentException
     */
    public function getRelatedModelClass(string $relation): string
    {
        $cast = $this->casts[$relation] ?? null;

        if (! is_string($cast) || ! is_subclass_of($cast, self::class)) {
            throw new InvalidArgumentException("Navigation property [$relation] is not cast to an AbacusModel on [".static::class.'].');
        }

        return $cast;
    }

    /**
     * Determine if the model has a named scope.
     */
    public function hasNamedScope(string $scope): bool
    {
        return method_exists($this, 'scope'.ucfirst($scope)) || static::isScopeMethodWithAttribute($scope);
    }

    /**
     * Apply the given named scope if possible.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function callNamedScope(string $scope, array $parameters = []): mixed
    {
        if ($this->isScopeMethodWithAttribute($scope)) {
            return $this->{$scope}(...$parameters);
        }

        return $this->{'scope'.ucfirst($scope)}(...$parameters);
    }

    /**
     * Determine if the given method has a scope attribute.
     */
    protected static function isScopeMethodWithAttribute(string $method): bool
    {
        if (method_exists(static::class, $method)) {
            $reflectionMethod = new ReflectionMethod(static::class, $method);

            return ! $reflectionMethod->isPrivate() && $reflectionMethod->getAttributes(Scope::class) !== [];
        }

        return false;
    }

    /**
     * Handle dynamic method calls into the query builder.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo(static::query(), $method, $parameters);
    }

    /**
     * Handle dynamic static method calls into the model.
     *
     * @param  array<int, mixed>  $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::query()->$method(...$parameters);
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
        } catch (Exception) {
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
