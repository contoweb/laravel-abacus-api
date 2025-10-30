<?php

namespace Contoweb\AbacusRestOdata\Models;

use Contoweb\AbacusRestOdata\AbacusRestService;
use Contoweb\AbacusRestOdata\AbacusQueryBuilder;

abstract class AbacusRestModel
{
    protected static string $resource;
    protected array $attributes = [];
    protected array $original = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
    }

    /**
     * Query Builder erstellen
     */
    public static function query(): AbacusQueryBuilder
    {
        $service = app(AbacusRestService::class);
        return new AbacusQueryBuilder($service, static::$resource);
    }

    /**
     * Alle Entities abrufen
     */
    public static function all(): array
    {
        $results = static::query()->get();
        return static::hydrate($results);
    }

    /**
     * Entity via Primary Key finden
     */
    public static function find(mixed $id): ?static
    {
        $result = static::query()->find($id);
        return $result ? new static($result) : null;
    }

    /**
     * Where-Query starten
     */
    public static function where(string $field, string $operator, mixed $value): AbacusQueryBuilder
    {
        return static::query()->where($field, $operator, $value);
    }

    /**
     * Select-Query starten
     */
    public static function select(array|string $fields): AbacusQueryBuilder
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
    public static function with(array|string $relations): AbacusQueryBuilder
    {
        return static::query()->with($relations);
    }

    /**
     * Entity erstellen
     */
    public static function create(array $attributes): static
    {
        $service = app(AbacusRestService::class);
        $result = $service->create(static::$resource, $attributes);

        return new static($result);
    }

    /**
     * Entity speichern (create oder update)
     */
    public function save(): static
    {
        $service = app(AbacusRestService::class);

        if (isset($this->attributes['Id'])) {
            $result = $service->update(static::$resource, $this->attributes['Id'], $this->getDirty());
            $this->attributes = array_merge($this->attributes, $result);
        } else {
            $result = $service->create(static::$resource, $this->attributes);
            $this->attributes = $result;
        }

        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Entity aktualisieren
     */
    public function update(array $attributes = []): static
    {
        if (!empty($attributes)) {
            foreach ($attributes as $key => $value) {
                $this->setAttribute($key, $value);
            }
        }

        return $this->save();
    }

    /**
     * Entity löschen
     */
    public function delete(): bool
    {
        if (!isset($this->attributes['Id'])) {
            return false;
        }

        $service = app(AbacusRestService::class);
        return $service->delete(static::$resource, $this->attributes['Id']);
    }

    /**
     * Array von Daten in Model-Instanzen umwandeln
     */
    protected static function hydrate(array $items): array
    {
        return array_map(fn($item) => new static($item), $items);
    }

    /**
     * Attribute abrufen
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Attribute setzen
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Alle Attribute abrufen
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Model als Array zurückgeben
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Model als JSON zurückgeben
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->attributes, $options);
    }

    /**
     * Prüfen ob Attribute geändert wurden
     */
    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return ($this->attributes[$key] ?? null) !== ($this->original[$key] ?? null);
        }

        return $this->attributes !== $this->original;
    }

    /**
     * Geänderte Attribute abrufen
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
     * Originale Attribute wiederherstellen
     */
    public function fresh(): ?static
    {
        if (!isset($this->attributes['Id'])) {
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
     * Resource-Name abrufen
     */
    public static function getResource(): string
    {
        return static::$resource;
    }
}