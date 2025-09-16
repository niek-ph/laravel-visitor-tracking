<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates tables with bigInteger id type', function () {
    Config::set('visitor-tracking.id_type', 'bigInteger');
    Config::set('visitor-tracking.users.id_type', 'bigInteger');

    // Create users table first
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->timestamps();
    });

    // Run the migration
    $this->artisan('migrate', ['--path' => 'vendor/niek-ph/laravel-visitor-tracking/database/migrations']);

    // Assert table structure
    expect(Schema::hasTable('visitors'))->toBeTrue();
    expect(Schema::hasTable('events'))->toBeTrue();

    // Check column types
    $visitorColumns = Schema::getColumnListing('visitors');
    expect($visitorColumns)->toContain('id', 'user_id');

    $eventColumns = Schema::getColumnListing('events');
    expect($eventColumns)->toContain('id', 'visitor_id');
});

it('creates tables with uuid id type', function () {
    Config::set('visitor-tracking.id_type', 'uuid');
    Config::set('visitor-tracking.users.id_type', 'uuid');

    // Create users table with UUID
    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('email')->unique();
        $table->timestamps();
    });

    // Run the migration
    $this->artisan('migrate', ['--path' => 'vendor/niek-ph/laravel-visitor-tracking/database/migrations']);

    expect(Schema::hasTable('visitors'))->toBeTrue();
    expect(Schema::hasTable('events'))->toBeTrue();

    // Verify UUID columns exist
    $visitorColumns = Schema::getColumnListing('visitors');
    expect($visitorColumns)->toContain('id', 'user_id');

    $eventColumns = Schema::getColumnListing('events');
    expect($eventColumns)->toContain('id', 'visitor_id');
});

it('creates tables with ulid id type', function () {
    Config::set('visitor-tracking.id_type', 'ulid');
    Config::set('visitor-tracking.users.id_type', 'ulid');

    // Create users table with ULID
    Schema::create('users', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->string('email')->unique();
        $table->timestamps();
    });

    // Run the migration
    $this->artisan('migrate', ['--path' => 'vendor/niek-ph/laravel-visitor-tracking/database/migrations']);

    expect(Schema::hasTable('visitors'))->toBeTrue();
    expect(Schema::hasTable('events'))->toBeTrue();

    $visitorColumns = Schema::getColumnListing('visitors');
    expect($visitorColumns)->toContain('id', 'user_id');

    $eventColumns = Schema::getColumnListing('events');
    expect($eventColumns)->toContain('id', 'visitor_id');
});
