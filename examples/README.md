# Example Models & Components

This directory contains example implementations to help you get started with the Abacus API package.

## Models

Models represent OData entitie/endpoints and provide a fluent interface for querying and manipulating data:

```php
// Query with models:
$products = Product::query()->where('Price', '>', 100)->get();

// Without models:
$products = Abacus::get('Products')->filter('Price gt 100')->all();
```

## Components

Components wrap nested OData complex types for object-style access:

```php
// With components:
$length = $product->Measurements->Length;

// Without components:
$length = $product->Measurements['Length'];
```

## Generate Your Own

```bash
php artisan make:abacus-model Account --resource=Accounts
```

## 📖 Resources

- [Main README](../README.md) - Full documentation
- [Abacus API Hub](https://apihub.abacus.ch/) - Abacus API reference
