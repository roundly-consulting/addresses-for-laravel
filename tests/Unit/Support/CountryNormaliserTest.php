<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Exceptions\InvalidCountryException;
use RoundlyConsulting\Addresses\Support\CountryNormaliser;

it('uppercases and trims a valid alpha-2 code', function () {
    expect(CountryNormaliser::normalise(' sk '))->toBe('SK');
});

it('accepts a valid alpha-3 code', function () {
    expect(CountryNormaliser::normalise('svk'))->toBe('SVK');
});

it('rejects malformed codes', function (string $value) {
    CountryNormaliser::normalise($value);
})->throws(InvalidCountryException::class)->with([
    'too long' => ['XYZQ'],
    'numeric' => ['1'],
    'single letter' => ['S'],
    'empty' => ['   '],
]);

it('checks the shape only: an unassigned code passes and alpha-3 is never mapped to alpha-2', function () {
    expect(CountryNormaliser::normalise('xx'))->toBe('XX')
        ->and(CountryNormaliser::normalise('svk'))->not->toBe('SK');
});
