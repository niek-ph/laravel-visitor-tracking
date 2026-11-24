<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    $this->withUnencryptedCookie(config('visitor-tracking.cookie_name'), 'anon_returning:'.time());
});

/**
 * A user that also exists in the users table, for the foreign key on Postgres and MySQL.
 */
function userWithId(int $id): User
{
    DB::table('users')->insertOrIgnore(['id' => $id]);

    $user = new User;
    $user->id = $id;

    return $user;
}

it('keeps the account link when the visitor is anonymous again', function () {
    $tag = 'anon_returning:'.time();

    $loggedIn = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => chromeUserAgent()]);
    $loggedIn->cookies->set(config('visitor-tracking.cookie_name'), $tag);
    $loggedIn->setUserResolver(fn () => userWithId(7));

    $loggedOut = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => chromeUserAgent()]);
    $loggedOut->cookies->set(config('visitor-tracking.cookie_name'), $tag);

    new InsertEventsJob([new PageViewEvent($loggedIn)])->handle();
    new InsertEventsJob([new PageViewEvent($loggedOut)])->handle();

    expect(Visitor::sole()->user_id)->toBe(7);
});

it('links the visitor to an account through identify', function () {
    $request = Request::create('/inloggen', 'POST', server: ['HTTP_USER_AGENT' => chromeUserAgent()]);

    VisitorTracking::identify($request, userWithId(42)->id, ['method' => 'password']);
    app()->terminate();
    handleQueuedTrackingJobs();

    $visitor = Visitor::sole();

    expect($visitor->user_id)->toBe(42)
        ->and($visitor->events->pluck('name')->all())->toBe(['identify']);
});

it('links visitors to the user of the configured guard', function () {
    Config::set('auth.guards.customer', ['driver' => 'session', 'provider' => 'users']);
    Config::set('visitor-tracking.users.guard', 'customer');

    $this->actingAs(userWithId(1));
    $this->app['auth']->guard('customer')->setUser(userWithId(2));

    $this->get('/', ['User-Agent' => chromeUserAgent()]);
    handleQueuedTrackingJobs();

    expect(Visitor::sole()->user_id)->toBe(2);
});
