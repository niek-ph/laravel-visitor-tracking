<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\Jobs\TrackEventJob;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

uses(RefreshDatabase::class);

it('tracks visitors and stores them in database with events', function () {
    // Disable queue processing to run jobs synchronously
    Queue::fake();

    // Make a request to trigger visitor tracking
    $response = $this->get('/');
    $response->assertStatus(200);

    // Get the visitor tag from the cookie
    $cookie = $response->getCookie(config('visitor-tracking.cookie_name'), false);
    $visitorTag = $cookie->getValue();

    // Manually dispatch the tracking job to simulate the tracking process
    $request = request();
    $request->headers->set('User-Agent', 'Test Browser');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $event = new PageViewEvent($request);
    TrackEventJob::dispatch($visitorTag, $event, now());

    // Process the job
    Queue::assertPushed(TrackEventJob::class);

    // Run the job to actually store data
    $job = new TrackEventJob($visitorTag, $event, now());
    $job->handle();

    // Assert visitor was created in database
    $visitorModel = VisitorTracking::$visitorModel;
    $this->assertDatabaseHas($visitorModel::make()->getTable(), [
        'tag' => $visitorTag,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Browser',
        'user_id' => null,
    ]);

    // Assert event was created in database
    $eventModel = VisitorTracking::$eventModel;
    $visitor = $visitorModel::where('tag', $visitorTag)->first();

    $this->assertDatabaseHas($eventModel::make()->getTable(), [
        'visitor_id' => $visitor->id,
        'name' => 'page_view',
        'url' => 'http://localhost',
    ]);

    // Assert relationships work
    expect($visitor->events)->toHaveCount(1);
    expect($visitor->events->first()->name)->toBe('page_view');
    expect($visitor->events->first()->visitor->tag)->toBe($visitorTag);
});

it('tracks authenticated user visits and stores user_id', function () {
    Queue::fake();

    // Create a user and authenticate
    $user = new \Illuminate\Foundation\Auth\User;
    $user->id = 456;

    $response = $this->actingAs($user)->get('/');
    $response->assertStatus(200);

    $cookie = $response->getCookie(config('visitor-tracking.cookie_name'), false);
    $visitorTag = $cookie->getValue();

    // Simulate tracking with authenticated user
    $request = request();
    $request->setUserResolver(fn () => $user);
    $request->headers->set('User-Agent', 'Test Browser');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');

    $event = new PageViewEvent($request);
    $job = new TrackEventJob($visitorTag, $event, now());
    $job->handle();

    // Assert visitor was created with user_id
    $visitorModel = VisitorTracking::$visitorModel;
    $this->assertDatabaseHas($visitorModel::make()->getTable(), [
        'tag' => $visitorTag,
        'user_id' => 456,
        'ip_address' => '192.168.1.1',
    ]);

    // Assert event was created
    $eventModel = VisitorTracking::$eventModel;
    $visitor = $visitorModel::where('tag', $visitorTag)->first();

    $this->assertDatabaseHas($eventModel::make()->getTable(), [
        'visitor_id' => $visitor->id,
        'name' => 'page_view',
    ]);

    expect($visitorTag)->toStartWith('user_456:');
});

it('updates existing visitor on subsequent visits', function () {
    Queue::fake();

    // First visit
    $response = $this->get('/');
    $cookie = $response->getCookie(config('visitor-tracking.cookie_name'), false);
    $visitorTag = $cookie->getValue();

    $request = request();
    $request->headers->set('User-Agent', 'Test Browser v1');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $event1 = new PageViewEvent($request);
    $job1 = new TrackEventJob($visitorTag, $event1, now());
    $job1->handle();

    // Second visit with different user agent
    $request->headers->set('User-Agent', 'Test Browser v2');
    $event2 = new PageViewEvent($request);
    $job2 = new TrackEventJob($visitorTag, $event2, now()->addMinutes(5));
    $job2->handle();

    // Assert only one visitor record exists but it's updated
    $visitorModel = VisitorTracking::$visitorModel;
    $visitors = $visitorModel::where('tag', $visitorTag)->get();
    expect($visitors)->toHaveCount(1);

    $visitor = $visitors->first();
    expect($visitor->user_agent)->toBe('Test Browser v2'); // Should be updated

    // Assert two events exist for the same visitor
    expect($visitor->events)->toHaveCount(2);
    expect($visitor->events->pluck('name')->toArray())->toBe(['page_view', 'page_view']);
});
