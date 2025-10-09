<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

uses(RefreshDatabase::class);

function generateUserAgent(): string
{
    return uniqid('test_browser');
}

it('tracks visitors and stores them in database with events', function () {
    // Disable queue processing to run jobs synchronously
    Queue::fake();

    $userAgent = generateUserAgent();
    // Make a request to trigger visitor tracking
    $response = $this
        ->get('/', [
            'User-Agent' => $userAgent,
        ]);

    $response->assertStatus(200);
    // Get the visitor tag from the cookie
    $cookie = $response->getCookie(config('visitor-tracking.cookie_name'), false);
    $visitorTag = $cookie->getValue();

    // Process the queued job that was created by the middleware
    Queue::assertPushed(InsertEventsJob::class, function ($job) {
        // Execute the job to store the data
        $job->handle();

        return true;
    });

    // Assert visitor was created in database
    $visitorModel = VisitorTracking::$visitorModel;
    $this->assertDatabaseHas($visitorModel::make()->getTable(), [
        'tag' => $visitorTag,
        //        'ip_address' => '127.0.0.1',
        'user_agent' => $userAgent,
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

    $userAgent = generateUserAgent();

    $response = $this->actingAs($user)->get('/', [
        'User-Agent' => $userAgent,
    ]);
    $response->assertStatus(200);

    $cookie = $response->getCookie(config('visitor-tracking.cookie_name'), false);
    $visitorTag = $cookie->getValue();

    // Process the queued job that was created by the middleware
    Queue::assertPushed(InsertEventsJob::class, function ($job) {
        // Execute the job to store the data
        $job->handle();

        return true;
    });

    // Assert visitor was created with user_id
    $visitorModel = VisitorTracking::$visitorModel;
    $this->assertDatabaseHas($visitorModel::make()->getTable(), [
        'tag' => $visitorTag,
        'user_id' => 456,
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
    $userAgent = generateUserAgent();

    $response = $this->get('/', [
        'User-Agent' => $userAgent,
    ]);

    $cookie = $response->getCookie(config('visitor-tracking.cookie_name'), false);
    $visitorTag = $cookie->getValue();

    $response2 = $this->get('/', [
        'User-Agent' => $userAgent,
    ]);

    // Process the queued job that was created by the middleware
    Queue::assertPushed(InsertEventsJob::class, function ($job) {
        // Execute the job to store the data
        $job->handle();

        return true;
    });

    // Assert only one visitor record exists but it's updated
    $visitorModel = VisitorTracking::$visitorModel;
    $visitors = $visitorModel::where('tag', $visitorTag)->get();

    expect($visitors)->toHaveCount(1);

    $visitor = $visitors->first();

    // Assert two events exist for the same visitor
    expect($visitor->events)->toHaveCount(2);
    expect($visitor->events->pluck('name')->toArray())->toBe(['page_view', 'page_view']);
});

it('stores visitor tag in laravel context when existing cookie is found', function () {
    // Create a valid visitor tag manually
    $existingTag = 'anon_'.\Illuminate\Support\Str::uuid().':'.time();

    // Create a request with the existing cookie
    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->cookies->set(config('visitor-tracking.cookie_name'), $existingTag);

    // Clear any existing context
    \Illuminate\Support\Facades\Context::flush();

    // Create VisitorTag instance which should store the tag in context
    $visitorTag = new \NiekPH\LaravelVisitorTracking\VisitorTag($request);

    // Assert the visitor tag was stored in context
    expect(\Illuminate\Support\Facades\Context::get('visitor_tag'))->toBe($existingTag);
    expect($visitorTag->getTag())->toBe($existingTag);
});
