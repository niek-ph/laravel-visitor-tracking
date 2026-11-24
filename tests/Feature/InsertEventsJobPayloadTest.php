<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('visitor-tracking.enable_geo_ip_lookup', false);

    // Laravel's dotenv loader writes every .env value into $_SERVER and $_ENV.
    $_SERVER['VISITOR_TRACKING_FAKE_SECRET'] = 'fake-secret-value';
    $_ENV['VISITOR_TRACKING_FAKE_SECRET'] = 'fake-secret-value';
});

afterEach(function () {
    unset($_SERVER['VISITOR_TRACKING_FAKE_SECRET'], $_ENV['VISITOR_TRACKING_FAKE_SECRET']);
});

it('keeps environment variables out of the queued job payload', function () {
    Queue::fake();

    $this
        ->withServerVariables(['VISITOR_TRACKING_FAKE_SECRET' => 'fake-secret-value'])
        ->get('/', ['Sec-CH-UA-Platform' => '"Windows"'])
        ->assertOk();

    Queue::assertPushed(InsertEventsJob::class, function (InsertEventsJob $job) {
        $payload = serialize($job);

        expect($payload)
            ->not->toContain('VISITOR_TRACKING_FAKE_SECRET')
            ->not->toContain('fake-secret-value')
            ->toContain('sec-ch-ua-platform');

        return true;
    });
});
