<?php

namespace Contoweb\AbacusRestOdata;

class AbacusQueryBuilder
{
    protected AbacusRestService $service;
    protected string $resource;
    protected array $filters = [];
    protected array $selects = [];
    protected ?string $orderBy = null;
    protected ?int $top = null;
    protected array $expand = [];
    protected string $format = 'json';

    public function __construct(AbacusRestService $service, string $resource)
    {
        $this->service = $service;
        $this->resource = $resource;
    }

    /**
     * Filter mit unterstützten Operatoren: eq, lt, gt, le, ge
     * Beispiel: ->where('LastName', 'eq', 'Müller')
     */
    public function where(string $field, string $operator, mixed $value)
    {
        $allowedOperators = ['eq', 'lt', 'gt', 'le', 'ge'];

        if (!in_array($operator, $allowedOperators)) {
            throw new \InvalidArgumentException(
                "Operator '{$operator}' nicht unterstützt. Erlaubt: " . implode(', ', $allowedOperators)
            );
        }

        $formattedValue = $this->formatValue($value);
        $this->filters[] = "{$field} {$operator} {$formattedValue}";

        return $this;
    }

    /**
     * Convenience-Methode für Gleichheit
     */
    public function whereEquals(string $field, mixed $value)
    {
        return $this->where($field, 'eq', $value);
    }

    /**
     * Mehrere Filter kombinieren (AND-Verknüpfung)
     */
    public function whereAnd(string $field, string $operator, mixed $value)
    {
        return $this->where($field, $operator, $value);
    }

    /**
     * $select - Nur bestimmte Properties abfragen
     * Beispiel: ->select(['LastName', 'AddressNumber'])
     */
    public function select(array|string $fields)
    {
        $this->selects = array_merge(
            $this->selects,
            is_array($fields) ? $fields : func_get_args()
        );

        return $this;
    }

    /**
     * $top - Nur Top N Elemente zurückgeben
     * Beispiel: ->top(10)
     */
    public function top(int $limit)
    {
        $this->top = $limit;

        return $this;
    }

    /**
     * Alias für top() (Laravel-like)
     */
    public function limit(int $limit)
    {
        return $this->top($limit);
    }

    /**
     * Alias für top() (Laravel-like)
     */
    public function take(int $limit)
    {
        return $this->top($limit);
    }

    /**
     * $orderby - Sortierung nach Attribut (asc oder desc)
     * Beispiel: ->orderBy('LastName', 'desc')
     * WICHTIG: Nur ein orderBy möglich, weitere überschreiben vorherige
     */
    public function orderBy(string $field, string $direction = 'asc')
    {
        if (!in_array(strtolower($direction), ['asc', 'desc'])) {
            throw new \InvalidArgumentException("Direction muss 'asc' oder 'desc' sein");
        }

        $this->orderBy = "{$field} " . strtolower($direction);

        return $this;
    }

    /**
     * $expand - Navigation Properties erweitern
     * Beispiel: ->expand('Addresses') oder ->expand(['Addresses', 'Contacts'])
     */
    public function expand(array|string $relations)
    {
        $this->expand = array_merge(
            $this->expand,
            is_array($relations) ? $relations : func_get_args()
        );

        return $this;
    }

    /**
     * Alias für expand() (Laravel-like)
     */
    public function with(array|string $relations)
    {
        return $this->expand($relations);
    }

    /**
     * $format - Response-Format ändern (json, atom, xml)
     * Default: json
     */
    public function format(string $format)
    {
        if (!in_array($format, ['json', 'atom', 'xml'])) {
            throw new \InvalidArgumentException("Format muss 'json', 'atom' oder 'xml' sein");
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Query ausführen und Ergebnisse zurückgeben
     */
    public function get()
    {
        $result = $this->service->query($this->resource, $this->buildODataQuery());

        // JSON-Response enthält normalerweise 'value' Array
        return $result['value'] ?? $result;
    }

    /**
     * Ersten Treffer zurückgeben
     */
    public function first()
    {
        $this->top = 1;
        $result = $this->get();

        return is_array($result) && count($result) > 0 ? $result[0] : null;
    }

    /**
     * Entity via Primary Key abrufen
     */
    public function find($id)
    {
        return $this->service->find($this->resource, $id);
    }

    /**
     * Spezifische Property eines Entities abrufen
     * Beispiel: Subjects::query()->findProperty(2, 'LastName')
     */
    public function findProperty($id, string $property)
    {
        return $this->service->findProperty($this->resource, $id, $property);
    }

    /**
     * OData Query-Parameter zusammenbauen
     */
    protected function buildODataQuery(): array
    {
        $query = [];

        // $filter
        if (!empty($this->filters)) {
            $query['$filter'] = implode(' and ', $this->filters);
        }

        // $select
        if (!empty($this->selects)) {
            $query['$select'] = implode(',', $this->selects);
        }

        // $orderby
        if ($this->orderBy !== null) {
            $query['$orderby'] = $this->orderBy;
        }

        // $top
        if ($this->top !== null) {
            $query['$top'] = $this->top;
        }

        // $expand
        if (!empty($this->expand)) {
            $query['$expand'] = implode(',', $this->expand);
        }

        // $format
        if ($this->format !== 'json') {
            $query['$format'] = $this->format;
        }

        return $query;
    }

    /**
     * Werte für OData formatieren
     */
    protected function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            // Einfache Anführungszeichen escapen
            return "'" . str_replace("'", "''", $value) . "'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        return (string) $value;
    }

    /**
     * Debug: Query-Parameter anzeigen
     */
    public function toODataQuery(): array
    {
        return $this->buildODataQuery();
    }
}