<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\Models\VisitorEvent;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;

beforeEach(function () {
    startWithoutVisitorTrackingTables();
});

afterEach(function () {
    restoreDefaultVisitorTrackingSchema();
});

dataset('string id types', [
    'uuid' => ['uuid', '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/'],
    'ulid' => ['ulid', '/^[0-9a-hjkmnp-tv-z]{26}$/'],
]);

it('stores visitors and events with generated string ids', function (string $idType, string $pattern) {
    Config::set('visitor-tracking.database.id_type', $idType);
    visitorTrackingMigration()->up();

    $request = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => chromeUserAgent()]);
    $request->cookies->set(config('visitor-tracking.cookie_name'), 'anon_returning:'.time());

    new InsertEventsJob([new PageViewEvent($request), new PageViewEvent($request)])->handle();
    new InsertEventsJob([new PageViewEvent($request)])->handle();

    $visitor = Visitor::sole();

    expect($visitor->getKey())->toMatch($pattern)
        ->and($visitor->events)->toHaveCount(3)
        ->and($visitor->events->pluck('id')->all())->each->toMatch($pattern);
})->with('string id types');

it('generates string ids when the models are created', function (string $idType, string $pattern) {
    Config::set('visitor-tracking.database.id_type', $idType);
    visitorTrackingMigration()->up();

    $visitor = Visitor::fromRequest(Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => chromeUserAgent()]));
    $event = VisitorEvent::factory()->for($visitor)->create();

    expect($visitor->getKey())->toMatch($pattern)
        ->and($event->getKey())->toMatch($pattern)
        ->and($visitor->fresh()->events->sole()->is($event))->toBeTrue();
})->with('string id types');
