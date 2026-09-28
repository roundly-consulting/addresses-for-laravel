<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Facades\Addresses;

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Addresses::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});
