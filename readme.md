# Abacus REST API Package for Laravel

[![Tests](https://github.com/contoweb/laravel-abacus-api/actions/workflows/tests.yml/badge.svg)](https://github.com/contoweb/laravel-abacus-api/actions/workflows/tests.yml)
[![Code Style](https://github.com/contoweb/laravel-abacus-api/actions/workflows/code-style.yml/badge.svg)](https://github.com/contoweb/laravel-abacus-api/actions/workflows/code-style.yml)
[![Latest Stable Version](https://poser.pugx.org/contoweb/laravel-abacus-api/v/stable)](https://packagist.org/packages/contoweb/laravel-abacus-api)
[![License](https://poser.pugx.org/contoweb/laravel-abacus-api/license)](https://packagist.org/packages/contoweb/laravel-abacus-api)

Laravel package for the Abacus REST API with OData support and Eloquent-like models.

## Requirements

- **Laravel** 11.x or 12.x
- **PHP** 8.2, 8.3, or 8.4

## Features

- **Eloquent-like API** - Familiar Laravel syntax
- **OData Support** - Filter, Select, OrderBy, Top, Expand
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

### Publish Config

```bash
php artisan vendor:publish --tag=abacus-config
```

### Environment Variables

Add to your `.env` file:

```env
ABACUS_REST_API_URL=entity-api1-1.demo.abacus.ch
ABACUS_REST_API_MANDATE=7777
ABACUS_REST_API_CLIENT_ID=your-client-id
ABACUS_REST_API_CLIENT_SECRET=your-client-secret
```

## Quick Start

### 1. Create a Model

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

### 2. Generate IDE Helper

```bash
php artisan abacus:generate-ide-helper
```

This automatically generates PHPDoc for all properties based on endpoint definition files.

#### How It Works

The command:
1. Scans your models directory for model classes
2. For each model, looks for a definition file at `storage/app/abacus/endpoint-definitions/{resource}.json`
3. Extracts entity schema and generates IDE hints

#### Setting Up Definition Files

1. Download the OpenAPI/Swagger JSON for your endpoints from Abacus. Exapmle: Click button "DOWNLOAD JSON-FILE" on page https://apihub.abacus.ch/apis/2025/entity/products.api
2. Save them to `storage/app/abacus/endpoint-definitions/`
3. Name them after your resource (lowercase): `{resource}.json`

**Example:**

```php
<?php

namespace App\Models\Abacus;

use Contoweb\AbacusApi\Models\AbacusModel;

class Product extends AbacusModel
{
    protected static string $resource = 'Products';
}
```

Save the Products endpoint definition as: `storage/app/abacus/endpoint-definitions/products.json`

The IDE helper will extract the entity schema (e.g., `ProductData`) from the definition file and generate autocomplete hints for your `Product` model.

#### Fallback to API Metadata

If no local definition files are found, the IDE helper will automatically fetch metadata from the API endpoint. This will generate hints for all available entities.

#### Listing Available Entities

To see all available entity types:

```bash
php artisan abacus:generate-ide-helper --list
```

### 3. Use the Models

```php
use App\Models\Abacus\Subject;

/* All Subjects */
$subjects = Subject::all();

/* Find a Subject */
$subject = Subject::find(1);

/* Find a Subject with Expand and Select. 
The find method must be called last */
$subject = Subject::select(['ProductNumber'])
    ->expand(['StockBatches'])
    ->find(1);

/* With Filters */
$subjects = Subject::where('LastName', 'eq', 'Müller')
    ->where('City', 'eq', 'Zürich')
    ->orderBy('FirstName', 'asc')
    ->top(10)
    ->get();

/* Create */
$subject = Subject::create([
    'FirstName' => 'Max',
    'LastName' => 'Mustermann',
    'Email' => 'max@example.com',
]);

/* Update */
Subject::update(1, ['Email' => 'new@example.com']);

/* Delete */
$subject->delete(1);
```

## Usage

### Cursor Pagination

Handle large datasets efficiently using OData's `@odata.nextLink` pagination:

```php
/* Basic cursor pagination - loads all pages into memory */
$subjects = Subject::cursor()
    ->pages(100)  // Max 100 pages
    ->get();

/* Process items page-by-page without loading everything into memory */
Subject::pages(100)
    ->cursorWithCallback(function($items, $pageNumber) {
        // Process each page as it's loaded
        foreach ($items as $item) {
            DB::table('processed')->insert([
                'item_id' => $item->Id,
                'processed_at' => now(),
            ]);
        }
        
        Log::info("Processed page {$pageNumber}: {$items->count()} items");
    })
    ->get();
```

**When to use `cursorWithCallback()`:**
- Processing large datasets (10,000+ records)
- Memory-intensive operations
- Long-running batch jobs
- Real-time progress logging

**Note:** `cursorWithCallback()` automatically enables cursor pagination, so calling `cursor()` is optional.

**Configuration:**
```php
// config/abacus-api.php
'query_builder' => [
    'max_next_link_page_resolving' => 5  // Default page limit
]
```

### Query Builder

```php
/* Filter with supported operators: eq, lt, gt, le, ge */
Subject::where('LastName', 'eq', 'Müller')
    ->where('Active', 'eq', true)
    ->get();

/* Select specific properties */
Subject::select(['FirstName', 'LastName', 'Email'])
    ->get();

/* OrderBy (only one orderBy per query) */
Subject::orderBy('LastName', 'desc')
    ->get();

/* Top N elements */
Subject::top(10)->get();

/* Expand Navigation Properties */
Subject::expand('Addresses')->get();

/* Combined */
Subject::where('City', 'eq', 'Zürich')
    ->select(['FirstName', 'LastName', 'Email'])
    ->orderBy('LastName', 'asc')
    ->expand('Addresses')
    ->top(5)
    ->get();

/* Cursor Pagination - Process large datasets page by page */
Subject::pages(100)
    ->cursorWithCallback(function($items, $pageNumber) {
        foreach ($items as $subject) {
            // Process each item immediately
            $this->processSubject($subject);
        }
        Log::info("Processed page {$pageNumber} with {$items->count()} subjects");
    })
    ->get();

/* Cursor Pagination - Load all pages into memory */
Subject::cursor()
    ->pages(50)
    ->get(); // Returns Collection of all items
```

### CRUD Operations

```php
/* Create */
$subject = Subject::create([
    'FirstName' => 'Anna',
    'LastName' => 'Muster',
]);

/* Read */
$subject = Subject::find(1);
$subjects = Subject::all();

/* Update */
$subject->Email = 'new@example.com';
$subject->save();

/* or */
$subject->update(['Email' => 'new@example.com']);

/* Delete */
$subject->delete();
```

### Batch Requests

Execute multiple operations in a single HTTP request to reduce network overhead.

**IMPORTANT:** Batch requests are NOT transactional. If Operation 2 fails, Operation 1 remains persisted.
```php
use Contoweb\AbacusApi\Facades\Abacus;

/* Basic usage */
$results = Abacus::batch(
    Customer::batch()->find(123),
    Product::batch()->where('Price', 'gt', 100)->get(),
    Order::batch()->create(['CustomerId' => 456, 'Total' => 99.99])
)->send();

/* Mixed CRUD operations */
$results = Abacus::batch(
    Customer::batch()->find(100),
    Order::batch()->create(['CustomerId' => 200, 'Total' => 99.99]),
    Customer::batch()->update(100, ['Status' => 'Active']),
    Product::batch()->delete(999)
)->send();

/* Composite keys */
$results = Abacus::batch(
    StockBatch::batch()->find(['BatchNumber' => '5436', 'ProductId' => 12276]),
    StockBatch::batch()->update(
        ['BatchNumber' => '5436', 'ProductId' => 12276],
        ['Remark' => 'Updated']
    )
)->send();

/* Filter successful results */
$successfulModels = $results
    ->filter(fn($result) => $result->isSuccess())
    ->map(fn($result) => $result->getModel());
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

## Commands

### Create a Model

```bash
php artisan make:abacus-model Subject --resource=Subjects
```

### Generate IDE Helper

```bash
/* Generate IDE helper from API metadata */
php artisan abacus:generate-ide-helper

/* With custom output */
php artisan abacus:generate-ide-helper --output=_ide_helper_custom.php
```

## Configuration

The configuration file `config/abacus-api.php`:

```php
return [
    'rest_api' => [
        'url' => env('ABACUS_REST_API_URL'),
        'mandate' => env('ABACUS_REST_API_MANDATE'),
        'client_id' => env('ABACUS_REST_API_CLIENT_ID'),
        'client_secret' => env('ABACUS_REST_API_CLIENT_SECRET'),
    ],

    'ide_helper' => [
        'enabled' => env('ABACUS_IDE_HELPER_ENABLED', true),
        'output_file' => env('ABACUS_IDE_HELPER_OUTPUT', '_ide_helper_abacus.php'),
    ],

    'models_namespace' => env('ABACUS_MODELS_NAMESPACE', 'App\\Models\\Abacus'),
];
```

## Composer Scripts

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

$subjects = Subject::all();
$this->assertCount(1, $subjects);
```

## OData Features

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

## Example Models

```php
/* Subject (Contacts/Addresses) */
class Subject extends AbacusModel
{
    protected static string $resource = 'Subjects';
}

/* Invoice */
class Invoice extends AbacusModel
{
    protected static string $resource = 'Invoices';
}

/* Article */
class Article extends AbacusModel
{
    protected static string $resource = 'Articles';
}
```

## IDE Support

The package automatically generates `_ide_helper_abacus.php` with complete PHPDoc annotations:

```php
$subject = Subject::find(1);
$subject->FirstName /* ✅ Autocomplete works! */
$subject->Email     /* ✅ Type-hints available! */
```

### .gitignore

Add to your `.gitignore`:

```
_ide_helper_abacus.php
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

### Config not loading

```bash
php artisan config:clear
php artisan config:cache
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md)

## Contributing

Pull Requests are welcome!

## License

MIT License. See [LICENSE.md](LICENSE.md)

## Credits

- [Your Name](https://github.com/yourname)
- [All Contributors](../../contributors)

## Links

- [Abacus API Hub](https://apihub.abacus.ch/)
- [OData v4.0 Documentation](https://www.odata.org/documentation/)