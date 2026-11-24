<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runs the published migration on the test connection. Run these tests on every database with
 * DB_CONNECTION=pgsql or DB_CONNECTION=mysql, see TestCase::defineEnvironment().
 */
function visitorTrackingMigration(): object
{
    return include __DIR__.'/../../database/migrations/create_visitor_tracking_tables.php.stub';
}

function createAccountsTable(string $table, string $idType): void
{
    Schema::dropIfExists($table);
    Schema::create($table, fn (Blueprint $table) => match ($idType) {
        'uuid' => $table->uuid('id')->primary(),
        'ulid' => $table->ulid('id')->primary(),
        default => $table->id(),
    });
}

beforeEach(function () {
    visitorTrackingMigration()->down();

    if (! Schema::hasTable('users')) {
        createAccountsTable('users', 'bigInteger');
    }
});

afterEach(function () {
    Schema::dropIfExists('tracked_visitor_events');
    Schema::dropIfExists('tracked_visitors');

    // Leave the default schema for the other tests on Postgres and MySQL
    Config::set('visitor-tracking.database.id_type', 'bigInteger');
    Config::set('visitor-tracking.database.tables', ['visitors' => 'visitors', 'visitor_events' => 'visitor_events']);
    Config::set('visitor-tracking.users', ['id_type' => 'bigInteger', 'table' => 'users', 'column' => 'id']);

    $migration = visitorTrackingMigration();
    $migration->down();
    Schema::dropIfExists('tracked_accounts');
    $migration->up();
});

it('creates and drops the tables for every id type', function (string $idType) {
    Config::set('visitor-tracking.database.id_type', $idType);
    Config::set('visitor-tracking.users.id_type', $idType);
    Config::set('visitor-tracking.users.table', 'tracked_accounts');
    createAccountsTable('tracked_accounts', $idType);

    $migration = visitorTrackingMigration();
    $migration->up();

    expect(Schema::hasIndex('visitors', ['tag'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('visitors', ['user_id']))->toBeTrue()
        ->and(Schema::hasIndex('visitor_events', ['visitor_id']))->toBeTrue()
        ->and(collect(Schema::getForeignKeys('visitors'))->pluck('foreign_table')->all())->toBe(['tracked_accounts'])
        ->and(collect(Schema::getForeignKeys('visitor_events'))->pluck('foreign_table')->all())->toBe(['visitors'])
        ->and(Schema::getColumnType('visitor_events', 'data'))->toBe(match (DB::getDriverName()) {
            'pgsql' => 'jsonb',
            'mysql', 'mariadb' => 'json',
            default => 'text',
        });

    $migration->down();

    expect(Schema::hasTable('visitors'))->toBeFalse()
        ->and(Schema::hasTable('visitor_events'))->toBeFalse();
})->with(['bigInteger', 'uuid', 'ulid']);

it('uses the configured table names', function () {
    Config::set('visitor-tracking.database.tables', [
        'visitors' => 'tracked_visitors',
        'visitor_events' => 'tracked_visitor_events',
    ]);

    $migration = visitorTrackingMigration();
    $migration->up();

    expect(collect(Schema::getForeignKeys('tracked_visitor_events'))->pluck('foreign_table')->all())->toBe(['tracked_visitors']);

    $migration->down();

    expect(Schema::hasTable('tracked_visitors'))->toBeFalse()
        ->and(Schema::hasTable('tracked_visitor_events'))->toBeFalse();
});
