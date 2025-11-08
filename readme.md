# Abacus REST API Package for Laravel

[![Latest Version](https://img.shields.io/packagist/v/contoweb/laravel-abacus-api.svg?style=flat-square)](https://packagist.org/packages/contoweb/laravel-abacus-api)
[![License](https://img.shields.io/packagist/l/contoweb/laravel-abacus-api.svg?style=flat-square)](LICENSE.md)

Laravel package for the Abacus REST API with OData support and Eloquent-like models.

## Features

✅ **Eloquent-like API** - Familiar Laravel syntax
✅ **OData Support** - Filter, Select, OrderBy, Top, Expand
✅ **Type-Safe** - Full PHPDoc support
✅ **IDE Autocomplete** - Automatic IDE Helper generation
✅ **CRUD Operations** - Create, Read, Update, Delete
✅ **Query Builder** - Fluent interface for complex queries
✅ **Testable** - Easy mocking with Laravel HTTP Fake

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

This automatically generates PHPDoc for all properties from the Abacus Swagger JSON.

### 3. Use the Models

```php
use App\Models\Abacus\Subject;

/* All Subjects */
$subjects = Subject::all();

/* Find a Subject */
$subject = Subject::find(1);

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
$subject->Email = 'new@example.com';
$subject->save();

/* Delete */
$subject->delete();
```

## Usage

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
Subject::with('Addresses')->get();

/* Combined */
Subject::where('City', 'eq', 'Zürich')
    ->select(['FirstName', 'LastName', 'Email'])
    ->orderBy('LastName', 'asc')
    ->with('Addresses')
    ->top(5)
    ->get();
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
/* With default URL from config */
php artisan abacus:generate-ide-helper

/* With custom URL */
php artisan abacus:generate-ide-helper --url=https://custom.url/swagger.json

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
        'swagger_url' => env('ABACUS_SWAGGER_URL', '...'),
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