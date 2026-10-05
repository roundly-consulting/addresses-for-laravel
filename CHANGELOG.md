# Changelog

All notable changes to `addresses-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.0.1 - 2026-10-05

### Changed

- Documentation: the README hero image uses an absolute URL, so it renders on Packagist and other
  sites.
- Maintenance: `composer.json` `homepage` and `support.docs` link to the package documentation.

### Fixed

- `AddressCreated`, `AddressUpdated`, `AddressDeleted` and `PrimaryAddressChanged` implement
  `ShouldDispatchAfterCommit`: inside a host transaction they wait for its commit, and a rollback
  discards them. Outside a transaction they still dispatch at once.
- `Addresses::update()` writes against the stored row, not the caller's possibly stale copy: it
  demotes a primary promoted elsewhere, overwrites columns changed behind the copy, and decides a
  type change from the stored type. The caller's own unsaved changes are still written.
- A promotion no longer throws `ModelNotFoundException` when a concurrent write moves the row to
  another type between its read and the group lock: it re-reads the row under a lock and promotes
  it in its current group, and refuses it if it now belongs to another owner. `restore()` gets the
  same re-read, so it cannot bring an address back as a second live primary on MySQL.
- `restore()` dispatches `PrimaryAddressChanged` when the address comes back as the primary. It
  does not when the address comes back plain, and `restoreQuietly()` stays silent.
- `AddressFactory` builds the model configured in `addresses.model`, so a host subclass's
  `factory()` creates rows as that subclass.
- `php artisan about` shows `INVALID` for an invalid `addresses.model` or
  `addresses.normalise_country` instead of throwing.
- `Addresses::fake()`: `update()` updates and returns the passed address, like the real manager,
  instead of a clone, so a second update or a delete through the same variable shows in the
  fake's reads. `setPrimary()` and a promoting `update()` read the owner, type and trash state
  from the stored row, like the real manager.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- `HasAddresses` trait: attach any number of billing, shipping or other addresses to any
  Eloquent model through a polymorphic relation.
- `AddressType` enum (`Default`, `Billing`, `Shipping`, `Home`, `Work`, `Office`) with a `label()`
  for UIs.
- One public API in three layers: the `Addresses` facade, the injectable `AddressManager` behind
  it, and an action per use case. `Addresses::for($owner)` returns an `AddressBook` —
  `new()` (fluent `PendingAddress`), `add(AddressData)`, `primary(?AddressType)`,
  `ofType(AddressType)`, `all()` and an ownership-checked `setPrimary(Address)` that also refuses
  a soft-deleted address (`TrashedAddressException`); flat `Addresses::update()`,
  `Addresses::delete()` and `Addresses::countryName()`.
- At most one primary address per owner and type. Every promotion (`setPrimary()`, adding or
  updating with `isPrimary: true`) runs in one transaction under a lock on the owner's type
  group, reads ownership and trash state from the stored row, and on PostgreSQL and SQLite a
  partial unique index makes a second live primary impossible. A deleted primary keeps its flag,
  so `restore()` undoes the delete — as a plain address when another primary holds the slot.
- Model shortcuts on `HasAddresses` (`newAddress()`, `addAddress()`, `createAddress()`,
  `primaryAddress()`, `getPrimaryAddressOfType()`, `setPrimaryAddress()`, `addressBook()`, …)
  that delegate to the address book.
- `Addresses::fake()`: an `AddressManager` subtype that also takes over dependency injection,
  writes nothing, answers reads with its adds, updates, deletes and primary changes applied (so
  `primary()` matches the real manager), and records every add, update, delete and primary
  change — including through the trait — with `assertAdded/Updated/Deleted/PrimarySet()` and an
  `assertNothing*()` for each.
- Country-code normalisation (trim, upper-case, two/three-letter shape check — no country list is
  bundled), and a pluggable `CountryResolver` for country names and coordinates.
- Query scopes `primary()`, `ofType()` and `inCountry()`, and `formatted()` one-line addresses.
- `AddressCreated`, `AddressUpdated`, `AddressDeleted` and `PrimaryAddressChanged` events;
  `PrimaryAddressChanged` fires on every path that makes an address primary.
- `AddressResource` API resource with a stable JSON shape.
- Swappable address model via the `addresses.model` config key; `addresses.key_type`
  (`ADDRESSES_KEY_TYPE`) for UUID/ULID owners; `addresses.default_type` for addresses created
  without a type; a config-only facade alias (`addresses.facade_alias`, `null` skips it).
- `InteractsWithAddresses` test assertions (`assertHasAddress()`, `assertPrimaryAddress()`).

### Changed

- `Addresses::for($owner)` returns an `AddressBook` instead of a `PendingAddress`: start the
  builder with `Addresses::for($owner)->new()`.
- `Addresses::primaryFor($owner, $type)` → `Addresses::for($owner)->primary($type)`;
  `Addresses::ofType($owner, $type)` → `Addresses::for($owner)->ofType($type)`.
- `PendingAddress` is built by the address book (constructor now takes an `AddressBook` and is
  internal); `Address::markAsPrimary()` is removed — promote with
  `Addresses::for($owner)->setPrimary($address)`.
- The address factory builds plain addresses by default; use its `primary()` state for a primary.
- `AddressManager` is no longer `final` (the fake extends it) and is autowired.
