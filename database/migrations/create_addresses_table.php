<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('addresses.key_type');

        Schema::create('addresses', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('addressable', $keyType, nullable: false);
            $table->boolean('is_primary')->default(false);
            $table->string('type')->default('default');
            $table->string('name')->nullable();
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_iso')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
