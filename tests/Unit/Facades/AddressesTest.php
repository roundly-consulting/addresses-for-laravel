<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('resolves the facade to an address book', function () {
    $entity = TestModel::create();

    expect(Addresses::for($entity))->toBeInstanceOf(AddressBook::class);
});

it('registers the configured alias', function () {
    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Addresses');
});
