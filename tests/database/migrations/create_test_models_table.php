<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned entity addresses hang off. A test fixture, not shipped schema: `addressable`
 * is a polymorphic, deliberately unconstrained morph, so the owner's table is the host's
 * business and there is no order to pin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_models', function (Blueprint $table): void {
            $table->increments('id');
        });
    }
};
