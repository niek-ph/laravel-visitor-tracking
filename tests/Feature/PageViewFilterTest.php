<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

afterEach(function () {
    VisitorTracking::trackWhen(null);
});

it('does not track prefetches', function (string $header, string $value) {
    $this->get('/', [$header => $value])->assertOk();

    Queue::assertNothingPushed();
})->with([
    'Inertia and link prefetch' => ['Purpose', 'prefetch'],
    'speculation rules prerender' => ['Sec-Purpose', 'prefetch;prerender'],
    'Firefox prefetch' => ['X-Moz', 'prefetch'],
    'Safari preview' => ['X-Purpose', 'preview'],
]);

it('does not track HEAD requests', function () {
    $this->call('HEAD', '/')->assertOk();

    Queue::assertNothingPushed();
});

it('does not track requests that the trackWhen callback rejects', function () {
    VisitorTracking::trackWhen(fn (Request $request): bool => ! $request->hasHeader('X-Staff'));

    $this->get('/', ['X-Staff' => '1'])->assertOk();

    $staffRequest = Request::create('/', 'GET', server: ['HTTP_X_STAFF' => '1']);
    $this->app->instance('request', $staffRequest);
    VisitorTracking::track(new PageViewEvent($staffRequest));
    app()->terminate();

    Queue::assertNothingPushed();

    $this->get('/')->assertOk();

    Queue::assertPushed(InsertEventsJob::class, 1);
});

it('tracks a repeated page view of the same visitor once within the configured time', function () {
    Config::set('visitor-tracking.deduplicate_page_views_for', 30 * 60);

    $this->withUnencryptedCookie(config('visitor-tracking.cookie_name'), 'anon_returning:'.time());

    $this->get('/');
    $this->get('/');
    $this->get('/test-json');

    Queue::assertPushed(InsertEventsJob::class, 2);
});
