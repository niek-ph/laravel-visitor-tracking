<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function chromeUserAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
}

/**
 * Handle the tracking jobs pushed so far, in order, and start a fresh fake queue for the next ones.
 */
function handleQueuedTrackingJobs(): void
{
    $jobs = Queue::pushed(InsertEventsJob::class);

    Queue::fake();

    $jobs->each->handle();
}

function visitorTrackingMigration(): object
{
    return include __DIR__.'/../database/migrations/create_visitor_tracking_tables.php.stub';
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

/**
 * For tests that run the migration themselves: no visitor tables yet, and a users table for the foreign key.
 */
function startWithoutVisitorTrackingTables(): void
{
    visitorTrackingMigration()->down();

    if (! Schema::hasTable('users')) {
        createAccountsTable('users', 'bigInteger');
    }
}

/**
 * Leave the default schema behind, for the other tests on Postgres and MySQL.
 */
function restoreDefaultVisitorTrackingSchema(): void
{
    Schema::dropIfExists('tracked_visitor_events');
    Schema::dropIfExists('tracked_visitors');

    Config::set('visitor-tracking.database.id_type', 'bigInteger');
    Config::set('visitor-tracking.database.tables', ['visitors' => 'visitors', 'visitor_events' => 'visitor_events']);
    Config::set('visitor-tracking.users', ['id_type' => 'bigInteger', 'table' => 'users', 'column' => 'id']);

    $migration = visitorTrackingMigration();
    $migration->down();
    Schema::dropIfExists('tracked_accounts');
    $migration->up();
}
