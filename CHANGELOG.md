# Changelog

All notable changes to `addresses-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- `HasAddresses` trait: attach any number of billing, shipping or other addresses to any
  Eloquent model through a polymorphic relation.
- `AddressType` enum (`Default`, `Billing`, `Shipping`, `Home`, `Work`, `Office`) with a `label()`
  for UIs.
- Fluent `PendingAddress` builder via the `Addresses` facade or `$model->newAddress()`, plus
  `addAddress(AddressData)` for programmatic creation.
- Primary-address handling per owner and type: `markAsPrimary()`, `setPrimaryAddress()`,
  `primaryAddress()` and `getPrimaryAddressOfType()`.
- ISO 3166-1 country-code normalisation and validation, and a pluggable `CountryResolver` for
  country names and coordinates.
- Query scopes `primary()`, `ofType()` and `inCountry()`, and `formatted()` one-line addresses.
- `AddressCreated`, `AddressUpdated`, `AddressDeleted` and `PrimaryAddressChanged` events.
- `AddressResource` API resource with a stable JSON shape.
- Swappable address model via the `addresses.model` config key.
- `InteractsWithAddresses` test assertions (`assertHasAddress()`, `assertPrimaryAddress()`).
