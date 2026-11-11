<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressesException;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Http\Resources\AddressResource;
use RoundlyConsulting\Addresses\Rules\ValidAddressType;
use RoundlyConsulting\Addresses\Support\AddressTypes;
use RoundlyConsulting\Addresses\Support\DefaultAddressType;
use RoundlyConsulting\Addresses\Tests\TestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * `addresses.types` — the allow-list of address types. It ships the six AddressType values,
 * so an app that never touches it keeps exactly today's behaviour; an app that sets it can
 * store its own types (`registered`, `postal`, `operating`).
 */
function typedData(AddressType|string|null $type = null, bool $primary = false): AddressData
{
    return AddressData::make(city: 'Bratislava', street: 'Main 1', postalCode: '81101', countryIso: 'SK', type: $type, isPrimary: $primary);
}

function customTypes(): void
{
    config()->set('addresses.types', [
        'default',
        'billing',
        'registered',
        'postal' => 'Postal address',
        'operating' => ['label' => 'Operating site'],
    ]);
}

it('ships the six AddressType values as the configured types', function (): void {
    expect(Addresses::types())->toBe(['default', 'billing', 'shipping', 'home', 'work', 'office'])
        ->and(config('addresses.types'))->toBe(array_map(fn (AddressType $type): string => $type->value, AddressType::cases()));
});

it('falls back to the six AddressType values when the key is not set', function (mixed $configured): void {
    config()->set('addresses.types', $configured);

    expect(Addresses::types())->toBe(['default', 'billing', 'shipping', 'home', 'work', 'office']);
})->with(['null' => [null], 'empty list' => [[]], 'blank string' => [' ']]);

it('reads a comma-separated string as a list', function (): void {
    config()->set('addresses.types', ' default, billing ,registered,');

    expect(Addresses::types())->toBe(['default', 'billing', 'registered']);
});

it('stores a configured custom type through every creation path', function (Closure $create): void {
    customTypes();

    $address = $create(TestModel::create());

    expect($address->type)->toBe('registered')
        ->and($address->typeKey())->toBe('registered')
        ->and($address->fresh()?->type)->toBe('registered')
        ->and($address->fresh()?->getRawOriginal('type'))->toBe('registered');
})->with([
    'book add()' => [fn (TestModel $owner) => Addresses::for($owner)->add(typedData('registered'))],
    'builder type()' => [fn (TestModel $owner) => Addresses::for($owner)->new()
        ->type('registered')->in('Bratislava')->at('Main 1')->postalCode('81101')->country('SK')->save()],
    'trait createAddress()' => [fn (TestModel $owner) => $owner->createAddress(
        city: 'Bratislava', street: 'Main 1', postalCode: '81101', countryIsoCode: 'SK', type: 'registered',
    )],
    'padded upper-case string' => [fn (TestModel $owner) => Addresses::for($owner)->add(typedData(' REGISTERED '))],
    'new AddressData()' => [fn (TestModel $owner) => Addresses::for($owner)->add(new AddressData(
        city: 'Bratislava', street: 'Main 1', postalCode: '81101', countryIso: 'SK', type: 'registered',
    ))],
]);

it('keeps the enum for the built-in types, given as a case or a string', function (): void {
    $fromCase = Addresses::for(TestModel::create())->add(typedData(AddressType::Billing));
    $fromString = Addresses::for(TestModel::create())->add(typedData('billing'));

    expect($fromCase->type)->toBe(AddressType::Billing)
        ->and($fromString->type)->toBe(AddressType::Billing)
        ->and($fromString->fresh()?->type)->toBe(AddressType::Billing)
        ->and(typedData('billing')->type)->toBe(AddressType::Billing)
        ->and(typedData('billing')->typeKey())->toBe('billing');
});

it('refuses a type that is not configured, on every write path', function (Closure $write): void {
    customTypes();

    $write(TestModel::create());
})->with([
    'AddressData::make()' => [fn () => typedData('warehouse')],
    'new AddressData()' => [fn () => new AddressData(city: 'A', street: 'B', postalCode: 'C', countryIso: 'SK', type: 'warehouse')],
    'a built-in case left out of the list' => [fn () => typedData(AddressType::Office)],
    'a blank string' => [fn () => typedData('  ')],
    'builder type()' => [fn (TestModel $owner) => Addresses::for($owner)->new()->type('warehouse')],
    'trait createAddress()' => [fn (TestModel $owner) => $owner->createAddress(
        city: 'Bratislava', street: 'Main 1', postalCode: '81101', countryIsoCode: 'SK', type: 'warehouse',
    )],
    'model attribute' => [fn (TestModel $owner) => Addresses::for($owner)->add(typedData('registered'))->type = 'warehouse'],
    'model attribute, not a string' => [fn (TestModel $owner) => Addresses::for($owner)->add(typedData('registered'))->type = 42],
    'factory' => [fn (TestModel $owner) => Address::factory()->for($owner, 'addressable')->ofType('warehouse')->create()],
])->throws(InvalidAddressTypeException::class);

it('names the refused type and the config key', function (): void {
    expect(fn () => typedData('warehouse'))->toThrow(
        InvalidAddressTypeException::class,
        'The address type [warehouse] is not one of the configured address types (addresses.types).',
    )->and(fn () => typedData('warehouse'))->toThrow(AddressesException::class);
});

it('reads and re-saves a row whose type is no longer configured', function (): void {
    customTypes();
    $address = Addresses::for(TestModel::create())->add(typedData('postal'));

    config()->set('addresses.types', ['default']);

    $fresh = Address::query()->findOrFail($address->getKey());

    expect($fresh->type)->toBe('postal')
        ->and($fresh->toArray()['type'])->toBe('postal');

    $fresh->type = 'postal';
    $fresh->name = 'Renamed';
    $fresh->save();

    expect($fresh->fresh()?->type)->toBe('postal')
        ->and($fresh->fresh()?->name)->toBe('Renamed');
});

it('serialises the type as its key, built-in or custom', function (): void {
    customTypes();
    $owner = TestModel::create();
    $billing = Addresses::for($owner)->add(typedData(AddressType::Billing));
    $postal = Addresses::for($owner)->add(typedData('postal'));

    expect($billing->toArray()['type'])->toBe('billing')
        ->and($postal->toArray()['type'])->toBe('postal')
        ->and($postal->fresh()?->toArray()['type'])->toBe('postal')
        ->and(AddressResource::make($postal)->resolve()['type'])->toBe('postal')
        ->and(AddressResource::make($billing)->resolve()['type'])->toBe('billing');
});

it('reads an owner\'s addresses by a custom type, through the book, the trait and the scope', function (): void {
    customTypes();
    $owner = TestModel::create();
    $registered = Addresses::for($owner)->add(typedData('registered', primary: true));
    Addresses::for($owner)->add(typedData('postal', primary: true));
    Addresses::for($owner)->add(typedData(AddressType::Billing));

    expect(Addresses::for($owner)->ofType('registered')->modelKeys())->toBe([$registered->getKey()])
        ->and(Addresses::for($owner)->primary('registered')?->is($registered))->toBeTrue()
        ->and(Addresses::for($owner)->primary(' Registered ')?->is($registered))->toBeTrue()
        ->and($owner->primaryAddress('registered')?->is($registered))->toBeTrue()
        ->and($owner->getAddressOfType('registered')?->is($registered))->toBeTrue()
        ->and($owner->getPrimaryAddressOfType('registered')?->is($registered))->toBeTrue()
        ->and($owner->addressesOfType('postal'))->toHaveCount(1)
        ->and(Address::query()->ofType(AddressType::Billing)->count())->toBe(1)
        ->and(Address::query()->where('type', 'registered')->count())->toBe(1);
});

it('keeps one primary per custom type group', function (): void {
    customTypes();
    $owner = TestModel::create();
    $first = Addresses::for($owner)->add(typedData('registered', primary: true));
    $second = Addresses::for($owner)->add(typedData('registered', primary: true));
    $postal = Addresses::for($owner)->add(typedData('postal', primary: true));

    expect($first->fresh()?->is_primary)->toBeFalse()
        ->and($second->fresh()?->is_primary)->toBeTrue()
        ->and($postal->fresh()?->is_primary)->toBeTrue();

    Addresses::update($second, typedData('postal', primary: true));

    expect($second->fresh()?->type)->toBe('postal')
        ->and($second->fresh()?->is_primary)->toBeTrue()
        ->and($postal->fresh()?->is_primary)->toBeFalse();
});

it('labels the built-in types through the translations', function (): void {
    expect(Addresses::typeLabel(AddressType::Billing))->toBe('Billing')
        ->and(AddressType::Shipping->label())->toBe('Shipping')
        ->and(Addresses::typeLabel('office'))->toBe('Office');

    app()->setLocale('sk');

    expect(Addresses::typeLabel(AddressType::Billing))->toBe('Fakturačná')
        ->and(AddressType::Shipping->label())->toBe('Doručovacia');
});

it('labels a custom type from the config, else as a headline of its key', function (): void {
    customTypes();
    config()->set('addresses.types', [...config('addresses.types'), 'head_office', 'billing' => 'Invoices']);
    $address = Addresses::for(TestModel::create())->add(typedData('operating'));

    expect(Addresses::typeLabel('postal'))->toBe('Postal address')
        ->and(Addresses::typeLabel('operating'))->toBe('Operating site')
        ->and(Addresses::typeLabel('registered'))->toBe('Registered')
        ->and(Addresses::typeLabel('head_office'))->toBe('Head Office')
        ->and(Addresses::typeLabel(AddressType::Billing))->toBe('Invoices')
        ->and(AddressType::Billing->label())->toBe('Invoices')
        ->and($address->typeLabel())->toBe('Operating site');
});

it('treats a null or blank configured label as no label', function (): void {
    config()->set('addresses.types', ['registered' => null, 'postal' => ' ', 'operating' => ['label' => null]]);

    expect(Addresses::types())->toBe(['registered', 'postal', 'operating'])
        ->and(Addresses::typeLabel('registered'))->toBe('Registered')
        ->and(Addresses::typeLabel('postal'))->toBe('Postal')
        ->and(Addresses::typeLabel('operating'))->toBe('Operating');
});

it('reads and writes a null type as null, as the enum cast did', function (): void {
    $address = new Address;

    expect($address->type)->toBeNull()
        ->and($address->typeKey())->toBe('');

    $address->type = 'billing';
    $address->type = null;

    expect($address->type)->toBeNull()
        ->and($address->toArray())->toHaveKey('type', null);
});

it('answers reads by a custom type through the fake', function (): void {
    customTypes();
    $owner = TestModel::create();
    $stored = Addresses::for($owner)->add(typedData('registered', primary: true));
    $fake = Addresses::fake();

    $added = Addresses::for($owner)->add(typedData('postal', primary: true));

    expect(Addresses::for($owner)->ofType('postal')->all())->toBe([$added])
        ->and(Addresses::for($owner)->primary('registered')?->is($stored))->toBeTrue()
        ->and(Addresses::for($owner)->primary('postal'))->toBe($added);

    $fake->assertAdded($owner, fn (AddressData $data): bool => $data->type === 'postal');
});

it('uses a configured custom type as the default type', function (): void {
    customTypes();
    config()->set('addresses.default_type', 'Postal');

    $address = Addresses::for(TestModel::create())->add(typedData());

    expect($address->type)->toBe('postal')
        ->and(DefaultAddressType::type())->toBe('postal')
        ->and(fn () => DefaultAddressType::resolve())->toThrow(InvalidAddressTypeException::class, '[postal]');
});

it('refuses a default type that is not configured', function (): void {
    config()->set('addresses.types', ['registered', 'postal']);

    typedData();
})->throws(InvalidAddressTypeException::class, '[default]');

it('builds factory addresses of a custom type, and of the default type when default is left out', function (): void {
    customTypes();
    $owner = TestModel::create();

    expect(Address::factory()->for($owner, 'addressable')->ofType('registered')->create()->type)->toBe('registered')
        ->and(Address::factory()->for($owner, 'addressable')->create()->type)->toBe(AddressType::Default);

    config()->set('addresses.types', ['registered', 'postal']);
    config()->set('addresses.default_type', 'postal');

    expect(Address::factory()->for($owner, 'addressable')->create()->type)->toBe('postal');
});

it('validates request input against the configured types', function (): void {
    customTypes();

    $passes = fn (mixed $value): bool => Validator::make(['type' => $value], ['type' => ['required', new ValidAddressType]])->passes();

    expect($passes('registered'))->toBeTrue()
        ->and($passes('Billing'))->toBeTrue()
        ->and($passes(AddressType::Billing))->toBeTrue()
        ->and($passes('office'))->toBeFalse()
        ->and($passes('warehouse'))->toBeFalse()
        ->and($passes(['registered']))->toBeFalse()
        ->and(Validator::make(['type' => 'x'], ['type' => new ValidAddressType])->errors()->first('type'))
        ->toBe('The type field must be one of the configured address types.');

    app()->setLocale('sk');

    expect(Validator::make(['type' => 'x'], ['type' => new ValidAddressType])->errors()->first('type'))
        ->toBe('Pole type musí byť jeden z nakonfigurovaných typov adresy.');
});

it('answers allows() and resolve() for the configured types', function (): void {
    customTypes();

    expect(AddressTypes::allows('registered'))->toBeTrue()
        ->and(AddressTypes::allows(AddressType::Default))->toBeTrue()
        ->and(AddressTypes::allows(AddressType::Home))->toBeFalse()
        ->and(AddressTypes::resolve(' Billing '))->toBe(AddressType::Billing)
        ->and(AddressTypes::resolve('postal'))->toBe('postal')
        ->and(AddressTypes::key(AddressType::Home))->toBe('home');
});

it('refuses a malformed types config, naming the key', function (mixed $configured): void {
    config()->set('addresses.types', $configured);

    Addresses::types();
})->with([
    'not a list' => [42],
    'a non-string type' => [[42]],
    'a blank type' => [['billing', ' ']],
    'a type with a dot' => [['head.office']],
    'a type with a space' => [['head office']],
    'a label that is not a string' => [['postal' => 42]],
    'a definition label that is not a string' => [['postal' => ['label' => 42]]],
    'a definition with an unknown key' => [['postal' => ['icon' => 'map']]],
    'a type longer than the column' => [[str_repeat('a', 256)]],
])->throws(InvalidConfigurationException::class, 'addresses.types');
