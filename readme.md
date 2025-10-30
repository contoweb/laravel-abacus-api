# Abacus REST OData Package for Laravel

[![Latest Version](https://img.shields.io/packagist/v/your-vendor/abacus-rest-odata.svg?style=flat-square)](https://packagist.org/packages/your-vendor/abacus-rest-odata)
[![License](https://img.shields.io/packagist/l/your-vendor/abacus-rest-odata.svg?style=flat-square)](LICENSE.md)

Laravel package für die Abacus REST API mit OData-Unterstützung und Eloquent-ähnlichen Models.

## Features

✅ **Eloquent-ähnliche API** - Vertraute Laravel-Syntax  
✅ **OData-Unterstützung** - Filter, Select, OrderBy, Top, Expand  
✅ **Type-Safe** - Vollständige PHPDoc-Unterstützung  
✅ **IDE Autocomplete** - Automatische IDE Helper Generierung  
✅ **CRUD-Operationen** - Create, Read, Update, Delete  
✅ **Query Builder** - Fluent Interface für komplexe Queries  
✅ **Testbar** - Einfaches Mocking mit Laravel HTTP Fake

## Installation

```bash
composer require your-vendor/abacus-rest-odata
```

### Config publishen

```bash
php artisan vendor:publish --tag=abacus-config
```

### Umgebungsvariablen

Füge in `.env` hinzu:

```env
ABACUS_REST_API_URL=entity-api1-1.demo.abacus.ch
ABACUS_REST_API_MANDATE=7777
ABACUS_REST_API_CLIENT_ID=your-client-id
ABACUS_REST_API_CLIENT_SECRET=your-client-secret
```

## Quick Start

### 1. Model erstellen

```bash
php artisan make:abacus-model Subject --resource=Subjects
```

Erstellt:
```php
<?php

namespace App\Models\Abacus;

use YourVendor\AbacusRestOdata\Models\AbacusRestModel;

class Subject extends AbacusRestModel
{
    protected static string $resource = 'Subjects';
}
```

### 2. IDE Helper generieren

```bash
php artisan abacus:generate-ide-helper
```

Dies generiert automatisch PHPDoc für alle Properties aus dem Abacus Swagger JSON.

### 3. Models verwenden

```php
use App\Models\Abacus\Subject;

// Alle Subjects
$subjects = Subject::all();

// Subject finden
$subject = Subject::find(1);

// Mit Filtern
$subjects = Subject::where('LastName', 'eq', 'Müller')
    ->where('City', 'eq', 'Zürich')
    ->orderBy('FirstName', 'asc')
    ->top(10)
    ->get();

// Erstellen
$subject = Subject::create([
    'FirstName' => 'Max',
    'LastName' => 'Mustermann',
    'Email' => 'max@example.com',
]);

// Aktualisieren
$subject->Email = 'new@example.com';
$subject->save();

// Löschen
$subject->delete();
```

## Verwendung

### Query Builder

```php
// Filter mit unterstützten Operatoren: eq, lt, gt, le, ge
Subject::where('LastName', 'eq', 'Müller')
    ->where('Active', 'eq', true)
    ->get();

// Select spezifischer Properties
Subject::select(['FirstName', 'LastName', 'Email'])
    ->get();

// OrderBy (nur ein orderBy pro Query)
Subject::orderBy('LastName', 'desc')
    ->get();

// Top N Elemente
Subject::top(10)->get();

// Expand Navigation Properties
Subject::with('Addresses')->get();

// Kombiniert
Subject::where('City', 'eq', 'Zürich')
    ->select(['FirstName', 'LastName', 'Email'])
    ->orderBy('LastName', 'asc')
    ->with('Addresses')
    ->top(5)
    ->get();
```

### CRUD Operationen

```php
// Create
$subject = Subject::create([
    'FirstName' => 'Anna',
    'LastName' => 'Muster',
]);

// Read
$subject = Subject::find(1);
$subjects = Subject::all();

// Update
$subject->Email = 'new@example.com';
$subject->save();

// oder
$subject->update(['Email' => 'new@example.com']);

// Delete
$subject->delete();
```

### Direkt mit Service arbeiten

```php
use YourVendor\AbacusRestOdata\AbacusRestService;

$service = app(AbacusRestService::class);

// Query
$result = $service->query('Subjects', [
    '$filter' => "LastName eq 'Müller'",
    '$top' => 10,
]);

// Metadata
$metadata = $service->metadata();

// Entity IDs
$entities = $service->listEntityIds();
```

## Commands

### Model erstellen

```bash
php artisan make:abacus-model Subject --resource=Subjects
```

### IDE Helper generieren

```bash
# Mit Standard-URL aus Config
php artisan abacus:generate-ide-helper

# Mit eigener URL
php artisan abacus:generate-ide-helper --url=https://custom.url/swagger.json

# Mit anderem Output
php artisan abacus:generate-ide-helper --output=_ide_helper_custom.php
```

## Konfiguration

Die Konfigurationsdatei `config/abacus-rest-odata.php`:

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

Füge in deine `composer.json` hinzu für automatische IDE Helper Generierung:

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

// HTTP Requests mocken
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

### Unterstützte Filter-Operatoren

- `eq` - Gleich
- `lt` - Kleiner als
- `gt` - Größer als
- `le` - Kleiner oder gleich
- `ge` - Größer oder gleich

### Unterstützte Query-Optionen

- `$filter` - Filter-Bedingungen
- `$select` - Property-Auswahl
- `$orderby` - Sortierung (nur eine pro Query)
- `$top` - Limit
- `$expand` - Navigation Properties laden
- `$format` - Response-Format (json, atom, xml)

## Beispiel-Models

```php
// Subject (Kontakte/Adressen)
class Subject extends AbacusRestModel
{
    protected static string $resource = 'Subjects';
}

// Invoice (Rechnungen)
class Invoice extends AbacusRestModel
{
    protected static string $resource = 'Invoices';
}

// Article (Artikel)
class Article extends AbacusRestModel
{
    protected static string $resource = 'Articles';
}
```

## IDE Support

Das Package generiert automatisch `_ide_helper_abacus.php` mit vollständigen PHPDoc-Annotations:

```php
$subject = Subject::find(1);
$subject->FirstName // ✅ Autocomplete funktioniert!
$subject->Email     // ✅ Type-Hints verfügbar!
```

### .gitignore

Füge hinzu:
```
_ide_helper_abacus.php
```

## Troubleshooting

### 401 Unauthorized

- Prüfe Client ID und Secret in `.env`
- Prüfe ob API-Zugang aktiviert ist

### Autocomplete funktioniert nicht

```bash
# IDE Helper neu generieren
php artisan abacus:generate-ide-helper

# PHPStorm Cache invalidieren
File → Invalidate Caches → Restart
```

### Config wird nicht geladen

```bash
php artisan config:clear
php artisan config:cache
```

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md)

## Contributing

Pull Requests sind willkommen!

## License

MIT License. Siehe [LICENSE.md](LICENSE.md)

## Credits

- [Your Name](https://github.com/yourname)
- [All Contributors](../../contributors)

## Links

- [Abacus API Hub](https://apihub.abacus.ch/)
- [OData v4.0 Documentation](https://www.odata.org/documentation/)