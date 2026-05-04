# Example Models & Components

This directory contains example implementations to help you get started with the Abacus API package.

## Models

Models represent OData entities/endpoints and provide a fluent interface for querying and manipulating data:

```php
// Query with models:
$products = Product::find(1);
```

## Components

Components wrap nested OData complex types for object-style access and support type casting:

```php
// With components:
$length = $product->Measurements->Length;

// Without components:
$length = $product->Measurements['Length'];
```

### Component Casting

Components support the same casting capabilities as models:

```php
class Measurements extends AbacusComponent
{
    protected array $casts = [
        'Length' => 'float',
        'Width' => 'float',
        'Height' => 'float',
        'UnitId' => 'int',
        'IsActive' => 'bool',
        'UpdatedAt' => 'datetime',
        'ExpiryDate' => 'datetime:Y-m-d',
        'Status' => StatusEnum::class,
        'Metadata' => 'json',
    ];
}

class Product extends AbacusModel {
    protected array $casts = [
        'Measurements' => Measurements::class,
    ];
}
```

## Generate Your Own

```bash
php artisan make:abacus-model Account --resource=Accounts
```

## 📖 Resources

- [Main README](../README.md) - Full documentation
- [Abacus API Hub](https://apihub.abacus.ch/) - Abacus API reference
