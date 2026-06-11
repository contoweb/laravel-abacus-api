# Abacus REST API Package for Laravel

<p>
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
    - [Retrieving Files](#retrieving-files)
    - [Bound OData Actions](#bound-odata-actions)
    - [Pagination](#pagination)
    - [Batch Requests](#batch-requests)
    - [Working Directly with the Service](#working-directly-with-the-service)
- [OData Action API](#odata-action-api)
    - [Price Finding](#price-finding)
- [AbaReports](#abareports)
    - [Creating Reports](#creating-reports)
    - [Using Reports](#using-reports)
    - [Parameter Validation](#parameter-validation)
    - [Mapping Reports](#mapping-reports)
- [IDE Support](#ide-support)
- [Troubleshooting](#troubleshooting)
- [Links](#links)

## Requirements

- **Laravel** 12.x or 13.x
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
ABACUS_REST_API_URL=entity-api1-1.demo.abacus.ch
ABACUS_REST_API_MANDATE=7777
ABACUS_REST_API_CLIENT_ID=your-client-id
ABACUS_REST_API_CLIENT_SECRET=your-client-secret
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

#### Supported Filter Operators

- `eq` - Equal
- `lt` - Less than
- `gt` - Greater than
- `le` - Less than or equal
- `ge` - Greater than or equal

#### Supported Query Options

- `$filter` - Filter conditions
- `$select` - Property selection
- `$orderby` - Sorting (only one per query)
- `$top` - Limit
- `$expand` - Load navigation properties
- `$format` - Response format (json, atom, xml)

### Example Models & Components

The `examples/` directory contains reference implementations to help you get started:

- **`examples/Models/`** - Example model classes (Product, Stock, ...)
- **`examples/Components/`** - Example component classes for nested OData schemas (Measurements, Weights, ...)

See [`examples/README.md`](examples/README.md)

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

#### Composite Keys

Some Abacus entities don't have a single numeric ID, but are uniquely identified by a **combination of multiple fields**.

Instead of passing a single value to `find()`, `update()`, or `delete()`, you pass an associative array with all key fields:

```php
$stockBatch = StockBatch::find([
    'BatchNumber' => '5436',
    'ProductId'   => 12276,
    'VariantId'   => 0,
]);
```

### Retrieving Files

Fetch binary content such as PDFs, images, and other files from Abacus entities.

#### Using Content Endpoint

Endpoints ending with `Documents` support Abacus "Dossiers" and allow file downloads via the `content()` method.<br> 
Example endpoints: `ProductDocuments`, `SalesOrderDocuments`, `AccountDocuments`

The `content()` method requires a document ID, which can be retrieved by expanding the `Documents` navigation property:

```php
use App\Models\Abacus\ProductDocument;

/* Retrieve the document */
$document = Product::find(1)
    ->expand('Documents')
    ->first();

/* Download the file content */
$binaryData = ProductDocument::query()->content($document->Id);

/* The $binaryData variable now contains the raw file content */
```

#### Using FileStream Endpoint

For attachments identified by composite keys (such as classification attachments), use the `fileStream()` method:

```php
use App\Models\Abacus\ProductClassificationElements;
use App\Models\Abacus\ProductClassificationAttachments;

/* Retrieve the classification */
$classification = ProductClassificationElements::paginate(1)->items()->first();

/* Download the file using a composite key */
$binaryData = ProductClassificationAttachments::query()->fileStream([
    'ClassificationId' => $classification->Id,
    'Language' => 'de',
    'SortOrder' => 1,
]);

/* The $binaryData variable now contains the raw file content */
```

### Bound OData Actions

Bound OData Actions allow you to trigger server-side operations on a specific entity.
For unbound actions on the mandant level (e.g. price finding), see the [OData Action API](#odata-action-api) chapter.

#### Parameters

- `$idOrCriteria` — Entity ID as `int`, `string`, or [composite key](#composite-keys)
- `$actionName` — Fully qualified action name (e.g. `ch.abacus.orde.TriggerSalesOrderNextStep`)
- `$data` — Optional action parameters as key-value pairs
- `$returnType` — Optional model class to map the response to

#### Return Value

- Returns `null` if the action responds with `204 No Content`
- Returns the raw response array if no `$returnType` is provided
- Returns a mapped model instance if `$returnType` is provided and the response contains a single object
- Returns a `Collection` of mapped models if `$returnType` is provided and the response contains a list

#### Usage

```php
SalesOrder::action(
    [
        'SalesOrderId'        => $salesOrderId,
        'SalesOrderBacklogId' => $salesOrderBacklogId,
    ],
    'ch.abacus.orde.TriggerSalesOrderNextStep',
    ['TypeOfPrinting' => 'DoNotPrint']
);
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

## OData Action API

In addition to the entity endpoints, the Abacus API provides unbound OData Actions that trigger server-side operations without being bound to a specific entity.

Each implemented action family is exposed through its own service under the `Contoweb\AbacusApi\Actions` namespace
and is documented below.

### Price Finding

The `PriceFindingService` covers the three price finding actions, which calculate product prices including
customer-specific pricing, discounts, graduations, taxes and fees:

| Action | Method | Purpose                                         |
|--------|--------|-------------------------------------------------|
| `FindProductPrice` | `findProductPrice()` | Price of a single product position              |
| `FindProductsPriceOverview` | `findProductsPriceOverview()` | Current prices of multiple positions            |
| `FindProductsPriceShoppingCart` | `findProductsPriceShoppingCart()` | Prices of multiple positions in a shopping cart |

#### Finding a Single Product Price

```php
use Contoweb\AbacusApi\Actions\PriceFinding\Facades\PriceFinder;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\RequestPosition;

$result = PriceFinder::findProductPrice(new ProductPricingRequest(
    customerNumber: 10042,
    currency: 'CHF',
    calculationDate: now(), // optional, defaults to the current date on the server
    position: new RequestPosition(productId: 1234, quantity: 5),
));

$result->position->perUnitValue->priceInclTax;
$result->position->taxDetail->rate;
```

#### Price Overview & Shopping Cart

Both actions accept the same `ProductsPricingRequest` with multiple positions and return the same response structure.
The difference: the overview returns the current price per product, while the shopping cart evaluates all positions
as one order and additionally applies order-related discounts (e.g. a product gets an extra discount when ordered
together with another one).

```php
use Contoweb\AbacusApi\Actions\PriceFinding\Facades\PriceFinder;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\DeliveryAddressCondition;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductsPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\RequestPosition;

$request = new ProductsPricingRequest(
    customerNumber: 10042,
    currency: 'CHF',
    positions: [
        new RequestPosition(productId: 1234, quantity: 2),
        new RequestPosition(productId: 5678),
    ],
    deliveryAddressCondition: new DeliveryAddressCondition(deliveryAddressNumber: 7),
    includeCalculationDocumentDiscount: true,
);

$overview = PriceFinder::findProductsPriceOverview($request);
$cart = PriceFinder::findProductsPriceShoppingCart($request);

foreach ($cart->positions as $position) {
    $position->perUnitValue->priceExclTax;
}

foreach ($cart->documentDiscounts as $discount) {
    $discount->percent;
}
```

#### Working with Results

`findProductPrice()` returns a `ProductPriceResult` with a single `position`; the other two actions return a
`ProductsPriceResult` with `positions` and `documentDiscounts` arrays. Each calculated position exposes:

- `perUnitValue` — prices incl./excl. tax, before and after discount
- `quantityDetail` — ordered, shipped and charged quantities
- `taxDetail` — tax code, rate and whether the price is inclusive
- `discountDetails`, `graduationDetails`, `feeDetails` — applied discounts, graduations and fees

All result objects keep the untouched decoded JSON response in `$result->raw`.

> **Note:** The properties `priceInclTaxBeforDiscount` / `priceExclTaxBeforDiscount` intentionally keep the
> "Befor" spelling of the Abacus API fields `PriceInclTaxBeforDiscount` / `PriceExclTaxBeforDiscount`.

#### Using Plain Arrays

All methods also accept the inner request object as a plain array (the wrapper key is added automatically):

```php
$result = PriceFinder::findProductPrice([
    'CustomerNumber' => 10042,
    'Currency' => 'CHF',
    'Position' => ['ProductId' => 1234, 'Quantity' => 5],
]);

$result->raw['Position']['PerUnitValue']['PriceInclTax'];
```

## AbaReports

This package supports Abacus AbaReports (non-OData endpoints) in addition to the OData Entity API.

### Creating Reports

#### Using the Artisan Command

```bash
php artisan make:abacus-report DepartmentsReport
```

#### Manual Creation

```php
<?php

namespace App\Services\Abacus\Reports;

use Contoweb\AbacusApi\Reports\Abstracts\Report;

class DepartmentsReport extends Report
{
    /**
     * The report name.
     */
    public function name(): string
    {
        return '%2F' . 'contacts_organisations.avw';
    }

    /**
     * Map the JSON record.
     */
    public function mapping(array $record): array
    {
        return $record
    }
}
```

### Using Reports

After running a report, you can retrieve the result in different formats:

**As a Collection**

Returns a collection of mapped objects or arrays, as defined in the report's `mapping()` method:

```php
$departments = AbaReport::run(new DepartmentsReport())->toCollection();
```

**As an Array**

Returns the report result as a decoded PHP array:

```php
$data = AbaReport::run(new DepartmentsReport())->toArray();
```

**Raw Output**

Returns the raw result string as returned by the API:

```php
$raw = AbaReport::run(new DepartmentsReport())->raw();
```

### Output Type

By default, reports use `json` as the output type. You can override this in your report class by setting the `$outputType` property:

```php
class DepartmentsReport extends Report
{
    protected string $outputType = 'json_userdef';
}
```

For a full list of available output types refer to the [Abacus AbaReport REST API documentation](https://downloads.abacus.ch/fileadmin/ablage/abaconnect/htmlfiles/docs/restapi/abacus_abareport_rest_api.html).

### Report Parameters

Parameters can be passed directly via the constructor or set via `setParameter()`:

```php
$report = new DepartmentsReport(['year' => 2024, 'month' => 1]);

$report = (new DepartmentsReport)->setParameters(['year' => 2024, 'month' => 1]);
```

### Parameter Validation

Reports can implement the `RequiresValidationRules` interface to validate parameters:

```php
class DepartmentsReport extends Report implements RequiresValidationRules
{
    public static function validationRules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'customer_id' => 'nullable|integer',
        ];
    }
}
```

If validation fails, a `ReportValidationException` is thrown with the validation error message.

### Mapping Reports

The `mapping()` method is called for each record in the report response. By default, you can simply return the raw `$record` array — the result will be a collection of plain arrays:

```php
public function mapping(array $record): array
{
    return $record;
}
```

If you want structured, type-safe objects instead, return a custom DTO. The result will then be a collection of those objects:

```php
public function mapping(array $record): SalesOrderDto
{
    return new SalesOrderDto(
        id: $record['ORDER_ID'] ?? null,
        customer: $record['CUSTOMER'] ?? null,
        amount: (float) ($record['AMOUNT'] ?? 0),
        date: $record['DATE'] ?? null,
    );
}
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
