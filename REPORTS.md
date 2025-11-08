# Abacus AbaReports Integration

This package now supports Abacus AbaReports (non-OData endpoints) in addition to the OData Entity API.

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Creating Reports](#creating-reports)
- [Using Reports](#using-reports)
- [Parameter Validation](#parameter-validation)
- [Caching](#caching)
- [Examples](#examples)
- [Architecture](#architecture)

## Installation

The reports functionality is included automatically when you install the package. No additional steps required.

## Configuration

Add the following environment variables to your `.env` file:

```env
# These are shared with OData (already configured)
ABACUS_REST_API_URL=your-abacus-instance.abacus.ch
ABACUS_REST_API_MANDATE=your_mandate
ABACUS_REST_API_CLIENT_ID=your_client_id
ABACUS_REST_API_CLIENT_SECRET=your_client_secret

# Reports-specific configuration (optional)
ABACUS_REPORTS_ENABLED=true
ABACUS_REPORTS_CACHE_ENABLED=false
ABACUS_REPORTS_CACHE_TTL=3600
ABACUS_REPORTS_POLL_INTERVAL=200000
ABACUS_REPORTS_MAX_POLL_ATTEMPTS=150
ABACUS_REPORTS_NAMESPACE=App\Services\Abacus\Reports
```

Publish the configuration file if you haven't already:

```bash
php artisan vendor:publish --tag=abacus-config
```

## Creating Reports

### Using the Artisan Command

Generate a new report class with its model:

```bash
php artisan make:abacus-report DepartmentsReport --model=Department
```

This creates two files:
- `app/Services/Abacus/Reports/DepartmentsReport.php` - The report class
- `app/Services/Abacus/Reports/Models/Department.php` - The model class

### Manual Creation

#### 1. Create a Report Model

```php
<?php

namespace App\Services\Abacus\Reports\Models;

use Contoweb\AbacusApi\Reports\Contracts\ReportModel;

class Department implements ReportModel
{
    public function __construct(
        public readonly ?string $contactNumber,
        public readonly ?string $subjectNumber,
        public readonly ?string $name,
    ) {
    }
}
```

#### 2. Create a Report Class

```php
<?php

namespace App\Services\Abacus\Reports;

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\ReportModel;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use App\Services\Abacus\Reports\Models\Department;

class DepartmentsReport implements Report, RequiresValidationRules
{
    /**
     * Get validation rules for report parameters
     */
    public static function validationRules(): array
    {
        return [
            'organization_number' => 'required|integer',
        ];
    }

    /**
     * Get the report name (with URL encoding)
     */
    public function name(): string
    {
        return config('abacus-api.rest_api.mandate') . '%2F' . 'contacts_organisations.avx';
    }

    /**
     * Map JSON record to report model
     */
    public function mapping(array $record): ReportModel
    {
        return new Department(
            $record['NR'] ?? null,
            $record['SUBJEKT_NR'] ?? null,
            $record['NAME'] ?? null,
        );
    }
}
```

## Using Reports

### Basic Usage

```php
use Contoweb\AbacusApi\Reports\Facades\AbaReport;
use App\Services\Abacus\Reports\DepartmentsReport;

$departments = AbaReport::parameter([
    'organization_number' => 123
])->collection(new DepartmentsReport());

/* $departments is now an Illuminate\Support\Collection of Department models */
foreach ($departments as $department) {
    echo $department->name;
}
```

### Using Dependency Injection

```php
use Contoweb\AbacusApi\Reports\AbacusReportsService;
use App\Services\Abacus\Reports\DepartmentsReport;

class YourController extends Controller
{
    public function __construct(
        private AbacusReportsService $reportsService
    ) {
    }

    public function index()
    {
        $departments = $this->reportsService
            ->parameter(['organization_number' => 123])
            ->collection(new DepartmentsReport());

        return view('departments.index', compact('departments'));
    }
}
```

## Parameter Validation

Reports can implement the `RequiresValidationRules` interface to validate parameters:

```php
class MyReport implements Report, RequiresValidationRules
{
    public static function validationRules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'customer_id' => 'nullable|integer',
        ];
    }

    /* ... other methods */
}
```

If validation fails, a `ReportValidationException` is thrown with the validation error message.

## Caching

### Enable Caching Per Request

```php
use Contoweb\AbacusApi\Reports\Facades\AbaReport;

/* Cache for 1 hour (3600 seconds) */
$departments = AbaReport::parameter(['organization_number' => 123])
    ->cache(3600)
    ->collection(new DepartmentsReport());

/* Custom cache key */
$departments = AbaReport::parameter(['organization_number' => 123])
    ->cache(3600, 'departments_org_123')
    ->collection(new DepartmentsReport());
```

### Enable Caching Globally

In your `.env`:

```env
ABACUS_REPORTS_CACHE_ENABLED=true
ABACUS_REPORTS_CACHE_TTL=3600
```

Cache keys are automatically generated based on the report name and parameters.

## Examples

### Example 1: Simple Report Without Validation

```php
class ProductsReport implements Report
{
    public function name(): string
    {
        return config('abacus-api.rest_api.mandate') . '%2F' . 'products.avx';
    }

    public function mapping(array $record): ReportModel
    {
        return new Product(
            $record['ID'] ?? null,
            $record['NAME'] ?? null,
            (float)($record['PRICE'] ?? 0),
        );
    }
}

/* Usage */
$products = AbaReport::collection(new ProductsReport());
```

### Example 2: Report With Date Range

```php
class SalesReport implements Report, RequiresValidationRules
{
    public static function validationRules(): array
    {
        return [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after:from_date',
        ];
    }

    public function name(): string
    {
        return config('abacus-api.rest_api.mandate') . '%2F' . 'sales_report.avx';
    }

    public function mapping(array $record): ReportModel
    {
        return new Sale(
            $record['ORDER_ID'] ?? null,
            $record['CUSTOMER'] ?? null,
            (float)($record['AMOUNT'] ?? 0),
            $record['DATE'] ?? null,
        );
    }
}

/* Usage */
$sales = AbaReport::parameter([
    'from_date' => '2024-01-01',
    'to_date' => '2024-12-31',
])->cache(1800)->collection(new SalesReport());
```

### Example 3: Error Handling

```php
use Contoweb\AbacusApi\Reports\Facades\AbaReport;
use Contoweb\AbacusApi\Reports\Exceptions\ReportValidationException;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;

try {
    $departments = AbaReport::parameter([
        'organization_number' => 123
    ])->collection(new DepartmentsReport());

    /* Process results */
} catch (ReportValidationException $e) {
    /* Handle validation errors */
    Log::error('Report validation failed: ' . $e->getMessage());
} catch (ReportExecutionException $e) {
    /* Handle execution errors */
    Log::error('Report execution failed: ' . $e->getMessage());
}
```

## Architecture

### Client Layer

- **BaseAbacusClient** - Shared OAuth2 authentication and HTTP methods
- **AbacusClient** - OData-specific functionality (extends BaseAbacusClient)
- **AbacusReportsClient** - Reports-specific functionality (extends BaseAbacusClient)

### Service Layer

- **AbacusService** - OData business logic
- **AbacusReportsService** - Reports business logic (validation, execution, caching)

### Reports Workflow

1. Submit report via POST to `/api/abareport/v1/report/{reportName}` with `outputType: json`
2. Receive job ID
3. Poll job status via GET to `/api/abareport/v1/jobs/{id}`
4. When state = "FinishedSuccess", fetch result
5. Get JSON output via GET to `/api/abareport/v1/jobs/{id}/output`
6. Parse JSON and map to models using report's `mapping()` method
7. Return collection of models

### Interfaces

- **Report** - Main interface for all reports
  - `name(): string` - Report identifier
  - `mapping(array): ReportModel` - JSON to model mapper

- **RequiresValidationRules** - Optional interface for parameter validation
  - `validationRules(): array` - Laravel validation rules

- **ReportModel** - Marker interface for report models

### Exceptions

- **ReportException** - Base exception
- **ReportValidationException** - Parameter validation failures
- **ReportExecutionException** - Report execution failures

## Troubleshooting

### Report Times Out

Increase polling attempts in `.env`:

```env
ABACUS_REPORTS_MAX_POLL_ATTEMPTS=300
```

### Report Returns No Data

Check the report name encoding. Forward slashes must be encoded as `%2F`:

```php
/* Correct */
$mandate . '%2F' . 'report.avx'

/* Incorrect */
$mandate . '/' . 'report.avx'
```

### JSON Parsing Errors

Ensure your `mapping()` method handles missing or null values:

```php
$record['FIELD_NAME'] ?? null
/* or with default value */
$record['FIELD_NAME'] ?? ''
/* or with type casting */
(int)($record['FIELD_NAME'] ?? 0)
```

## API Reference

### AbaReport Facade

```php
/* Set parameters */
AbaReport::parameter(array|string $parameters): self

/* Enable caching */
AbaReport::cache(int $ttl = 3600, ?string $cacheKey = null): self

/* Execute report and return collection */
AbaReport::collection(Report $report): Collection
```

### Configuration Keys

```php
'reports.enabled' => bool
'reports.cache_enabled' => bool
'reports.cache_ttl' => int (seconds)
'reports.poll_interval' => int (microseconds)
'reports.max_poll_attempts' => int
'reports.reports_namespace' => string /* Default: 'App\\Services\\Abacus\\Reports' */
```

## See Also

- [Abacus API Hub - AbaReports](https://apihub.abacus.ch/endpoints/notodata)
- [Abacus API Hub - AbaReport Entity](https://apihub.abacus.ch/apis/notodata/entity/aba-report.api)
- Main package README for OData functionality
