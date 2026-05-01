# Abacus REST API Package for Laravel

<p align="center">
    <img src="https://contoweb.ch/assets/bcbf8b02-ed68-4a2a-92e5-197366bcd673.png" alt="Banner" style="width: 100%; max-width: 800px;" />
</p>

[![Tests](https://github.com/contoweb/laravel-abacus-api/actions/workflows/tests.yml/badge.svg)](https://github.com/contoweb/laravel-abacus-api/actions/workflows/tests.yml)
[![Code Style](https://github.com/contoweb/laravel-abacus-api/actions/workflows/code-style.yml/badge.svg)](https://github.com/contoweb/laravel-abacus-api/actions/workflows/code-style.yml)
[![Latest Stable Version](https://poser.pugx.org/contoweb/laravel-abacus-api/v/stable)](https://packagist.org/packages/contoweb/laravel-abacus-api)
[![License](https://poser.pugx.org/contoweb/laravel-abacus-api/license)](https://packagist.org/packages/contoweb/laravel-abacus-api)

Laravel package for the Abacus REST API with OData support, Eloquent-like models, and AbaReports integration.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [OData Entity API](#odata)
    - [Create Your Own Model](#create-your-own-model)
    - [Use the Models](#use-the-models)
    - [CRUD Operations](#crud-operations)
    - [Pagination](#pagination)
    - [Batch Requests](#batch-requests)
    - [Working Directly with the Service](#working-directly-with-the-service)
- [AbaReports](#abareports)
    - [Creating Reports](#creating-reports)
    - [Using Reports](#using-reports)
    - [Parameter Validation](#parameter-validation)
    - [Report Examples](#report-examples)
- [IDE Support](#ide-support)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Links](#links)

## Requirements

- **Laravel** 11.x or 12.x
- **PHP** 8.2, 8.3, or 8.4

## Features

- **Eloquent-like API** - Familiar Laravel syntax
- **OData Support** - Filter, Select, OrderBy, Top, Expand
- **AbaReports** - Fetch AbaReport data
- **Type-Safe** - Full PHPDoc support
- **IDE Autocomplete** - Automatic IDE Helper generation
- **CRUD Operations** - Create, Read, Update, Delete
- **Batch Requests** - Multiple operations in a single HTTP request
- **Query Builder** - Fluent interface for complex queries
- **Testable** - Easy mocking with Laravel HTTP Fake

## Installation

```bash
composer require contoweb/laravel-abacus-api
```

Publish the config file:

```bash
php artisan vendor:publish --tag=abacus-config
```

## Configuration

### Environment Variables

Add to your `.env` file:

```env
# OData Entity API (required)
ABACUS_REST_API_URL=entity-api1-1.demo.abacus.ch
ABACUS_REST_API_MANDATE=7777
ABACUS_REST_API_CLIENT_ID=your-client-id
ABACUS_REST_API_CLIENT_SECRET=your-client-secret
 
# AbaReports (optional)
ABACUS_REPORTS_POLL_INTERVAL=200000
ABACUS_REPORTS_MAX_POLL_ATTEMPTS=150
ABACUS_REPORTS_NAMESPACE=App\Services\Abacus\Reports
```

## OData

### Create your own Model

You can easily create custom models for any Abacus endpoint.

```bash
php artisan make:abacus-model Subject --resource=Subjects
```

This creates:

```php
<?php

namespace App\Models\Abacus;

use Contoweb\AbacusApi\Models\AbacusModel;

class Subject extends AbacusModel
{
    protected static string $resource = 'Subjects';
}
```

### Use the Models

```php
use App\Models\Abacus\Subject;

/* Find a Subject */
$subject = Subject::find(1);

/* Find a Subject with Expand and Select. The find method must be called last */
$subject = Subject::select(['ProductNumber'])
    ->expand(['StockBatches'])
    ->find(1);

/* Filter with supported operators: eq, lt, gt, le, ge */
$subjects = Subject::where('LastName', 'eq', 'Müller')
    ->where('Active', 'eq', true)
    ->paginate()
    ->items();

/* Select specific properties */
$subject = Subject::select(['FirstName', 'LastName', 'Email'])
    ->paginate()
    ->items();

/* OrderBy (only one orderBy per query) */
$subject = Subject::orderBy('LastName', 'desc')
    ->paginate()
    ->items();

/* Expand Navigation Properties */
$subject = Subject::expand('Addresses')
    ->paginate()
    ->items();

/* Combined */
$subject = Subject::where('City', 'eq', 'Zürich')
    ->select(['FirstName', 'LastName', 'Email'])
    ->orderBy('LastName', 'asc')
    ->expand('Addresses')
    ->paginate();

/* Filter with OData Enum values */
use Contoweb\AbacusApi\ODataQueryString;

$subject = Product::where('Type', 'eq', ODataQueryString::enum('ch.abacus.orde.ProductType', 'Article'))
    ->paginate()
    ->items();
/* Results in: $filter=Type eq ch.abacus.orde.ProductType'Article' */
```

### Supported Filter Operators

- `eq` - Equal
- `lt` - Less than
- `gt` - Greater than
- `le` - Less than or equal
- `ge` - Greater than or equal

### Supported Query Options

- `$filter` - Filter conditions
- `$select` - Property selection
- `$orderby` - Sorting (only one per query)
- `$top` - Limit
- `$expand` - Load navigation properties
- `$format` - Response format (json, atom, xml)

### CRUD Operations

```php
/* Create */
$subject = Subject::create([
    'FirstName' => 'Anna',
    'LastName' => 'Muster',
]);

/* Read */
$subject = Subject::find(1);
$subjects = Subject::paginate()->items();

/* Update */
$subject->update(['Email' => 'new@example.com']);

/* Delete */
$subject->delete();
```

### Pagination

The Abacus OData API doesn't support fetching all records in a single request.
Instead, responses are returned in pages with a `nextLink` pointer to the next page. 
The `paginate()` method returns an OdataPaginator object that gives you explicit control over loading additional pages using this `nextLink`.

#### Usage

```php
/* Get first page with default limit */
$paginator = Subject::paginate();

/* Specify items per page using the $perPage parameter */
$paginator = Subject::where('Active', 'eq', true)->paginate(20);

/* Get the loaded items */
$items = $paginator->items();

/* Check if more pages exist */
if ($paginator->hasMorePages()) {
$paginator->nextPage(); /* Load next page and append to items */
}

/* Get the updated items collection */
$items = $paginator->items();
```

The `$perPage` parameter sets the OData $top option, controlling how many items are returned per page. If not specified, the API default limit applies.

```php
/* Load 10 items per page */
$paginator = Subject::paginate(10);

/* Load 50 items per page */
$paginator = Subject::paginate(50);
```

### Batch Requests

Execute multiple operations in a single HTTP request to reduce network overhead and improve performance.

**IMPORTANT:** Batch requests are NOT transactional in Abacus. If one request fails, the others may still be processed and persisted.

#### Overview

Batch requests allow you to combine multiple API calls into a single HTTP request, which:
- Reduces network round trips and latency
- Improves application performance
- Efficiently handles bulk operations
- Maintains individual operation independence (non-transactional)

#### Basic Usage (Recommended)

**Capture Pattern** - Write normal queries that automatically batch:

```php
use Contoweb\AbacusApi\Facades\Abacus;

/* Cleanest syntax - queries execute in batch context */
[$customer, $products, $order] = Abacus::batch(function() {
    return [
        Customer::find(123),
        Product::where('Price', 'gt', 100)->paginate(),
        Order::create(['CustomerId' => 456, 'Total' => 99.99]),
    ];
})->send()->mapped();

/* Results are ready to use immediately */
echo $customer->FirstName;
foreach ($products as $product) {
    echo $product->Name;
}
```

**Access Results by Index:**

```php
$results = Abacus::batch(function() {
    return [
        Customer::find(123),
        Product::where('Price', 'gt', 100)->paginate(),
        Order::create(['CustomerId' => 456, 'Total' => 99.99]),
    ];
})->send();

// Access results by index
$customer = $results[0]->mapped()->first();
$products = $results[1]->mapped();
$order = $results[2]->mapped()->first();
```

#### Progressive Building

Build batches dynamically based on conditions:

```php
$batch = Abacus::newBatch();

/* Add queries conditionally */
$batch->capture(function() {
    Customer::find(123);
});

if ($includeProducts) {
    $batch->capture(function() {
        Product::where('Active', 'eq', true)->paginate();
    });
}

if ($includeOrders) {
    $batch->capture(function() {
        Order::where('CustomerId', 'eq', 123)->paginate();
    });
}

/* Execute only the queries you added */
$results = $batch->send();
```

#### Accessing Results

Use array destructuring for clean result access:

```php
/* Destructure directly (recommended) */
[$customer, $products, $orders] = Abacus::batch(function() {
    return [
        Customer::find(123),
        Product::where('Price', 'gt', 100)->paginate(),
        Order::where('CustomerId', 'eq', 123)->paginate(),
    ];
})->send();

/* Or access by index */
$results = Abacus::batch(function() {
    return [Customer::find(123), Product::find(456)];
})->send();

$customer = $results[0]->mapped()->first();
$product = $results[1]->mapped()->first();
```

#### Mixed CRUD Operations

Combine different operation types in a single batch:

```php
[$found, $created, $updated, $deleted] = Abacus::batch(function() {
    return [
        Customer::find(100),                                    // GET
        Order::create(['CustomerId' => 200, 'Total' => 99.99]), // POST
        Customer::update(100, ['Status' => 'Active']),          // PATCH
        Product::delete(999),                                   // DELETE
    ];
})->send();
```

#### Composite Keys

Works seamlessly with composite key entities:

```php
[$stockBatch, $updated] = Abacus::batch(function() {
    return [
        StockBatch::find([
            'BatchNumber' => '5436',
            'ProductId' => 12276,
            'VariantId' => 0
        ]),
        StockBatch::update(
            ['BatchNumber' => '5436', 'ProductId' => 12276, 'VariantId' => 0],
            ['Remark' => 'Updated via batch']
        ),
    ];
})->send();
```

#### Response Handling

You can check the status of each operation individually:

```php
$results = Abacus::batch(function() {
    return [
        Customer::find(123),
        Product::find(999), /* Non-existent, will fail */
        Order::create(['CustomerId' => 456, 'Total' => 99.99]),
    ];
})->send();

if ($results->allSuccessful()) {
    /* All operations succeeded */
} 

if ($results->hasFailures()) {
    /* Some operations failed */
}

/* Filter by success/failure */
$successful = $results->successful(); /* Only successful responses */
$failed = $results->failed();         /* Only failed responses */

/* Extract all models from successful operations */
$allModels = $results->successful()->mapped();

/* Get error details from failed operations */
foreach ($results->failed() as $result) {
    echo "Status: {$result->status}\n";
    echo "Error: {$result->getError()}\n";
    echo "Message: {$result->getErrorMessage()}\n";
}
```

#### Error Handling

Handle partial failures gracefully:

```php
$results = Abacus::batch(function() {
    return [
        Customer::find(1),
        Product::find(999),  /* Will fail - non-existent */
        Order::find(1),
    ];
})->send();

/* Get errors collection */
$errors = $results->errors();
foreach ($errors as $error) {
    Log::error('Batch operation failed', [
        'status' => $error['status'],
        'error' => $error['error'],
        'message' => $error['message'],
    ]);
}

/* Continue with successful results */
$successfulData = $results->successful();
foreach ($successfulData as $result) {
    // Process successful results
    $models = $result->mapped();
}
```

#### Inspection Methods

Inspect batch contents before sending:

```php
$batch = Abacus::newBatch('customer-data-fetch');

/* Add queries via capture */
$batch->capture(function() {
    Customer::find(123);
    Order::where('CustomerId', 'eq', 123)->paginate();
});

/* Inspect before sending */
echo "Batch name: " . $batch->getName() . "\n";
echo "Item count: " . $batch->count() . "\n";
echo "Is empty: " . ($batch->isEmpty() ? 'yes' : 'no') . "\n";

/* Clear and rebuild if needed */
$batch->clear();
$batch->capture(function() {
    Customer::find(456);
});

$results = $batch->send();
```

#### Best Practices

**Batch Size Recommendations:**
- Keep batches under 50 operations for optimal performance
- For large datasets, process in chunks
- Monitor response times and adjust batch sizes accordingly

**Performance Tips:**
```php
/* Good: Targeted queries with filters */
$results = Abacus::batch(function() {
    return [
        Customer::where('Status', 'eq', 'Active')->select(['Id', 'Name'])->paginate(),
        Order::where('Date', 'gt', '2024-01-01')->paginate(),
    ];
})->send();

/* Avoid: Too many operations in a single batch */
/* Split into multiple batches if needed */
$batch1 = Abacus::batch(/* first 50 operations */)->send();
$batch2 = Abacus::batch(/* next 50 operations */)->send();
```

### Working Directly with the Service

```php
use Contoweb\AbacusApi\AbacusService;

$service = app(AbacusService::class);

/* Query */
$result = $service->query('Subjects', [
    '$filter' => "LastName eq 'Müller'",
    '$top' => 10,
]);

/* Metadata */
$metadata = $service->metadata();

/* Entity IDs */
$entities = $service->listEntityIds();
```

## AbaReports

This package supports Abacus AbaReports (non-OData endpoints) in addition to the OData Entity API.

### Creating Reports

#### Using the Artisan Command

```bash
php artisan make:abacus-report DepartmentsReport --model=Department
```

This creates two files:
- `app/Services/Abacus/Reports/DepartmentsReport.php` - The report class
- `app/Services/Abacus/Reports/Models/Department.php` - The model class

#### Manual Creation

**1. Create a Report Model**

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

**2. Create a Report Class**

```php
<?php

namespace App\Services\Abacus\Reports;

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\ReportModel;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use App\Services\Abacus\Reports\Models\Department;

class DepartmentsReport extends Report implements RequiresValidationRules
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
        return '%2F' . 'contacts_organisations.avx';
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

### Using Reports

**Via Facade:**

```php
use Contoweb\AbacusApi\Reports\Facades\AbaReport;
use App\Services\Abacus\Reports\DepartmentsReport;

$departments = AbaReport::collection(new DepartmentsReport('organization_number' => 123));

/* $departments is now an Illuminate\Support\Collection of Department models */
foreach ($departments as $department) {
    echo $department->name;
}
```

**Via Dependency Injection:**

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
            ->collection(new DepartmentsReport(['organization_number' => 123]));

        return view('departments.index', compact('departments'));
    }
}
```

### Output Type

By default, reports use `json` as the output type. You can override this in your report class by setting the `$outputType` property:

```php
class DepartmentsReport extends Report
{
    protected string $outputType = 'json_userdef';
}
```

### Report Parameters

Parameters can be passed directly via the constructor or set via `setParameter()`:

```php
$report = new DepartmentsReport(['year' => 2024, 'month' => 1]);

$report = (new DepartmentsReport)->setParameter(['year' => 2024, 'month' => 1]);
```

### Parameter Validation

Reports can implement the `RequiresValidationRules` interface to validate parameters:

```php
class DepartmentsReport implements Report, RequiresValidationRules
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

### Report Examples

**Report without validation:**

```php
class ProductsReport implements Report
{
    public function name(): string
    {
        return '%2F' . 'products.avx';
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

**Report with validation:**

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
        return '%2F' . 'sales_report.avx';
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
$sales = AbaReport::collection(new SalesReport([
    'from_date' => '2024-01-01',
    'to_date' => '2024-12-31',
]));
```

## Testing

```php
use Illuminate\Support\Facades\Http;
use App\Models\Abacus\Subject;

/* Mock HTTP Requests */
Http::fake([
    'entity-api1-1.demo.abacus.ch/*' => Http::response([
        'value' => [
            ['Id' => 1, 'FirstName' => 'Max', 'LastName' => 'Test'],
        ]
    ], 200)
]);

$subjects = Subject::paginate()->items();
$this->assertCount(1, $subjects);
```

## IDE Support

This package supports PHPDoc for all properties based on the Abacus OData metadata file:

```bash
php artisan abacus:generate-ide-helper
```

Add to your `.gitignore`:

```
_ide_helper_abacus.php
```

Add to your `composer.json` for automatic IDE Helper generation:

```json
{
  "scripts": {
    "post-update-cmd": [
      "@php artisan abacus:generate-ide-helper"
    ]
  }
}
```

#### How It Works

The command:
1. Reads the OData metadata XML file (by default bundled with the package under `resources/metadata/`)
2. Parses all `EntityType` definitions
3. Maps OData types to PHP types

#### Options

```bash
# Use a custom metadata XML file
php artisan abacus:generate-ide-helper --source=/absolute/path/to/metadata.xml
 
# Override the output file
php artisan abacus:generate-ide-helper --output=_ide_helper_abacus.php
```

## Troubleshooting

### 401 Unauthorized

- Check Client ID and Secret in `.env`
- Check if API access is enabled

### Autocomplete not working

```bash
/* Regenerate IDE Helper */
php artisan abacus:generate-ide-helper

/* Invalidate PHPStorm Cache */
File → Invalidate Caches → Restart
```

## Contributing

Pull Requests are welcome!

## License

MIT License. See [LICENSE.md](LICENSE.md)

## Links

- [Abacus API Hub](https://apihub.abacus.ch/)
- [Abacus AbaReport Entity](https://apihub.abacus.ch/apis/notodata/entity/aba-report.api)
- [OData v4.0 Documentation](https://www.odata.org/documentation/)
