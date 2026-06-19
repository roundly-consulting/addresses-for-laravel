<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\PendingAddress;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('resolves the facade to a pending builder', function () {
    $entity = TestModel::create();

    expect(Addresses::for($entity))->toBeInstanceOf(PendingAddress::class);
});

it('registers the configured alias', function () {
    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Addresses');
});
