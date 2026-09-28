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
- One public API in three layers: the `Addresses` facade, the injectable `AddressManager` behind
  it, and an action per use case. `Addresses::for($owner)` returns an `AddressBook` —
  `new()` (fluent `PendingAddress`), `add(AddressData)`, `primary(?AddressType)`,
  `ofType(AddressType)`, `all()` and an ownership-checked `setPrimary(Address)`; flat
  `Addresses::update()`, `Addresses::delete()` and `Addresses::countryName()`.
- Model shortcuts on `HasAddresses` (`newAddress()`, `addAddress()`, `createAddress()`,
  `primaryAddress()`, `getPrimaryAddressOfType()`, `setPrimaryAddress()`, `addressBook()`, …)
  that delegate to the address book.
- `Addresses::fake()`: an `AddressManager` subtype that also takes over dependency injection,
  writes nothing, merges added addresses into reads, and records every add, update, delete and
  primary change — including through the trait — with `assertAdded/Updated/Deleted/PrimarySet()`
  and an `assertNothing*()` for each.
- ISO 3166-1 country-code normalisation and validation, and a pluggable `CountryResolver` for
  country names and coordinates.
- Query scopes `primary()`, `ofType()` and `inCountry()`, and `formatted()` one-line addresses.
- `AddressCreated`, `AddressUpdated`, `AddressDeleted` and `PrimaryAddressChanged` events.
- `AddressResource` API resource with a stable JSON shape.
- Swappable address model via the `addresses.model` config key.
- `InteractsWithAddresses` test assertions (`assertHasAddress()`, `assertPrimaryAddress()`).

### Changed

- `Addresses::for($owner)` returns an `AddressBook` instead of a `PendingAddress`: start the
  builder with `Addresses::for($owner)->new()`.
- `Addresses::primaryFor($owner, $type)` → `Addresses::for($owner)->primary($type)`;
  `Addresses::ofType($owner, $type)` → `Addresses::for($owner)->ofType($type)`.
- `PendingAddress` is built by the address book (constructor now takes an `AddressBook` and is
  internal); `Address::markAsPrimary()` is internal — promote with
  `Addresses::for($owner)->setPrimary($address)`.
- `AddressManager` is no longer `final` (the fake extends it) and is autowired.
