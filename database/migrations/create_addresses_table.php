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

        $this->onePrimaryPerOwnerAndType();
    }

    /**
     * At most one live primary per owner + type, enforced by the engine. Trashed rows hold no
     * slot: a deleted primary keeps its flag so a restore can undo the delete, and the
     * model's restore() brings it back plain when another primary holds the slot by then.
     *
     * Promotion locks the owner's type group, which serialises it on MySQL/MariaDB and SQL
     * Server: their locking reads wait on a racing transaction's uncommitted rows. Postgres's
     * `FOR UPDATE` cannot see a row inserted after its snapshot, so two first addresses of a
     * type added as primary at once would both win — this partial unique index refuses the
     * second, and the promotion retries against the committed winner. SQLite gets the same
     * index (identical syntax), so the default test engine exercises the guard.
     */
    private function onePrimaryPerOwnerAndType(): void
    {
        $connection = Schema::getConnection();

        if (! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'create unique index %s on %s (%s) where %s and %s is null',
            $grammar->wrap($connection->getTablePrefix().'addresses_one_primary_per_type'),
            $grammar->wrapTable('addresses'),
            $grammar->columnize(['addressable_type', 'addressable_id', 'type']),
            $grammar->wrap('is_primary'),
            $grammar->wrap('deleted_at'),
        ));
    }
};
