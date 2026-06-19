# Addresses for Laravel

Store billing, shipping, or other addresses on any Eloquent model via a polymorphic
relationship. Add the `HasAddresses` trait to a model and attach as many typed addresses as
you need, with optional primary-address handling and a flexible `meta` payload.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/addresses-for-laravel
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="addresses-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="addresses-config"
```

## Configuration

The published config file (`config/addresses.php`) exposes a single key:

```php
use RoundlyConsulting\Addresses\Address;

return [

    // The Eloquent model used to store addresses. Override with your own class
    // (extending the package model) to add custom behaviour.
    'model' => Address::class,

];
```

| Key     | Type           | Default           | Purpose                                                                 |
|---------|----------------|-------------------|-------------------------------------------------------------------------|
| `model` | `class-string` | `Address::class`  | The model the `HasAddresses` trait reads and writes through. Swap it for a subclass to customise behaviour. |

## Usage

### Add the trait

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Traits\HasAddresses;

class Payer extends Model
{
    use HasAddresses;
}
```

### Create an address

```php
$payer = Payer::create(['name' => 'John Doe']);

$payer->createAddress(
    city: 'Pretty',
    street: 'Somewhere 1',
    postalCode: '123456',
    countryIsoCode: 'SK',
    name: 'My Office',
    isPrimary: true,
    type: 'office',
    meta: collect([
        'custom_data' => 'OK',
    ]),
);
```

`meta` is stored as JSON and cast back to an `Illuminate\Support\Collection`.

### Read addresses

```php
// All addresses (Eloquent collection)
$payer->addresses;

// First address of a given type
$payer->getAddressOfType(type: 'home');

// First *primary* address of a given type (or null)
$payer->getPrimaryAddressOfType(type: 'office');
```

### Manage the primary address

Each address can be promoted to primary for its owner + type. Promoting one demotes the
others in the same group:

```php
$address = $payer->getAddressOfType('office');

$address->markAsPrimary();        // promote this address, demote siblings
$address->markAsPrimary(false);   // demote every office address for this owner
```

### Use a custom model

Point the config at your own subclass to extend behaviour:

```php
namespace App\Models;

use RoundlyConsulting\Addresses\Address as BaseAddress;

class Address extends BaseAddress
{
    // ...
}
```

```php
// config/addresses.php
'model' => \App\Models\Address::class,
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
