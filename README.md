<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/addresses-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=addresses-for-laravel">
    <img src="art/hero.png" alt="Addresses for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/addresses-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/addresses-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/addresses-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/addresses-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/addresses-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/addresses-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
</p>
<!-- roundly-badges:end -->

# Addresses for Laravel

Store billing, shipping, or other addresses on any Eloquent model via a polymorphic
relationship. Add the `HasAddresses` trait to a model and attach as many typed addresses as
you need, with a fluent builder, a typed `AddressType` enum, primary-address handling, ISO
country normalisation, query scopes, events, an API resource, and test helpers.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

### Integrates with

- [`package-toolkit-for-laravel`](https://github.com/roundly-consulting/package-toolkit-for-laravel) —
  the package is bootstrapped with the toolkit's `PackageServiceProvider`, so its config, the
  publishable migration and the facade alias are wired through the shared builder, the configured
  model is resolved through the toolkit's `ModelResolver`, and `php artisan about` reports the
  addresses setup.

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/addresses-for-laravel
```

Publish and run the migration. The migration is **not** loaded automatically — publishing it is
required, and the published copy is yours to edit:

```bash
php artisan vendor:publish --tag="addresses-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="addresses-config"
```

## Configuration

The published config file (`config/addresses.php`):

```php
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;

return [
    'model' => Address::class,
    'default_type' => AddressType::Default->value,
    'normalise_country' => true,
    'country_resolver' => null,
    'facade_alias' => 'Addresses',
];
```

| Key                 | Type                  | Default              | Purpose                                                                                              |
|---------------------|-----------------------|----------------------|------------------------------------------------------------------------------------------------------|
| `model`             | `class-string`        | `Address::class`     | The model the trait/facade read and write through. Swap it for a subclass to customise behaviour.    |
| `default_type`      | `string`              | `'default'`          | The `AddressType` used when none is supplied.                                                         |
| `normalise_country` | `bool`                | `true`               | Trim, upper-case, and validate country codes as ISO 3166-1 alpha-2/alpha-3 on the way in.             |
| `country_resolver`  | `class-string\|null`  | `null`               | Optional host implementation of `CountryResolver` used to resolve country names (and geocode).        |
| `facade_alias`      | `string\|null`        | `'Addresses'`        | The class alias registered for the facade. Set to `null` to skip registering a global alias.          |

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

### Address types

`type` is a backed enum, `RoundlyConsulting\Addresses\Enums\AddressType`, with the cases
`Default`, `Billing`, `Shipping`, `Home`, `Work`, and `Office`. Each case has a `label()`
for UI. Address types are enum-only across the whole public API.

```php
use RoundlyConsulting\Addresses\Enums\AddressType;

AddressType::Office->value;   // 'office'
AddressType::Office->label(); // 'Office'
```

### Create an address — fluent builder

The facade and the `newAddress()` model helper return a fluent `PendingAddress` builder.
Country codes are normalised automatically:

```php
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Facades\Addresses;

Addresses::for($payer)
    ->type(AddressType::Office)
    ->name('HQ')
    ->primary()
    ->in('Bratislava')->at('Somewhere 1')->postalCode('81101')->country('sk')
    ->meta(['floor' => 3])
    ->save();

// or from the model
$payer->newAddress()
    ->type(AddressType::Home)
    ->in('Košice')->at('Main 2')->postalCode('04001')->country('SK')
    ->save();
```

Calling `save()` without `in()`, `at()`, `postalCode()`, and `country()` throws
`IncompleteAddressException`.

### Create an address — DTO / programmatic

```php
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;

$payer->addAddress(AddressData::make(
    city: 'Bratislava',
    street: 'Somewhere 1',
    postalCode: '81101',
    countryIso: 'SK',
    type: AddressType::Office,
    isPrimary: true,
));
```

The legacy `createAddress(...)` method is still available (with an enum `type` argument).

`meta` is stored as JSON and cast back to an `Illuminate\Support\Collection`.

### Read addresses

```php
use RoundlyConsulting\Addresses\Enums\AddressType;

$payer->addresses;                                   // all addresses
$payer->getAddressOfType(AddressType::Home);         // first of a type
$payer->getPrimaryAddressOfType(AddressType::Office); // first primary of a type (or null)
$payer->primaryAddress();                            // primary across all types
$payer->primaryAddress(AddressType::Office);         // primary of a type
$payer->addressesOfType(AddressType::Shipping);      // collection of a type
$payer->hasAddresses();                              // bool
```

### Query scopes

```php
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;

Address::query()->primary()->get();
Address::query()->ofType(AddressType::Billing)->get();
Address::query()->inCountry('SK')->get();
```

### Manage the primary address

Promoting one address demotes the others in the same owner + type group:

```php
$address = $payer->getAddressOfType(AddressType::Office);

$address->markAsPrimary();        // promote this, demote siblings
$address->markAsPrimary(false);   // demote every office address for this owner

// owner-guarded helper (rejects addresses owned by another model)
$payer->setPrimaryAddress($address);
```

### Format an address

```php
$payer->primaryAddress()?->formatted();
// "HQ, Somewhere 1, 81101 Bratislava, SK"

$address->formatted(' | '); // custom separator; empty parts are skipped
```

### Country names (optional resolver)

The package validates and normalises ISO country codes but ships no country-name list or
geocoder. To resolve names (or coordinates), implement `CountryResolver` in your app and
register it in config:

```php
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\DataTransferObjects\Coordinates;

class AppCountryResolver implements CountryResolver
{
    public function name(string $iso): ?string { /* ... */ }
    public function coordinates(AddressData $data): ?Coordinates { /* ... */ }
}
```

```php
// config/addresses.php
'country_resolver' => \App\Support\AppCountryResolver::class,
```

With a resolver bound, `$address->country_name` returns the resolved name; without one it is
`null`.

### Events

The package dispatches:

- `AddressCreated` — after an address is created
- `AddressUpdated` — after an address is updated through `UpdateAddressAction`
- `AddressDeleted` — after an address is deleted
- `PrimaryAddressChanged` — when an address is promoted to primary

Each event exposes the related `$address`.

### API resource

`RoundlyConsulting\Addresses\Http\Resources\AddressResource` renders an address with a
stable shape (id, type, name, lines, country ISO/name, `is_primary`, `formatted`, meta,
timestamps):

```php
use RoundlyConsulting\Addresses\Http\Resources\AddressResource;

return AddressResource::collection($payer->addresses);
```

### Use a custom model

Point the config at your own subclass:

```php
namespace App\Models;

use RoundlyConsulting\Addresses\Address as BaseAddress;

class Address extends BaseAddress
{
    protected $table = 'addresses';
}
```

```php
// config/addresses.php
'model' => \App\Models\Address::class,
```

## Testing

The `InteractsWithAddresses` trait adds expressive assertions to your tests:

```php
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Testing\InteractsWithAddresses;

uses(InteractsWithAddresses::class);

$this->assertHasAddress($payer, ['city' => 'Bratislava']);
$this->assertPrimaryAddress($payer, AddressType::Office);
```

Run the package test suite with:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=addresses-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation or a
monthly pledge on Patreon helps fund maintenance, new features and new packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
