<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\Models\VisitorEvent;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;
use NiekPH\LaravelVisitorTracking\TrackingEvents\TrackingEvent;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function searchEvent(Request $request): TrackingEvent
{
    return new class($request) extends TrackingEvent
    {
        public function getName(): string
        {
            return 'search';
        }
    };
}

it('stores the landing page once the browser sends the cookie back', function () {
    $tag = $this->get('/?utm_source=nieuwsbrief', ['User-Agent' => chromeUserAgent()])
        ->getCookie(config('visitor-tracking.cookie_name'), false)
        ->getValue();

    handleQueuedTrackingJobs();

    expect(Visitor::count())->toBe(0);

    $this->withUnencryptedCookie(config('visitor-tracking.cookie_name'), $tag)
        ->get('/test-json', ['User-Agent' => chromeUserAgent()]);

    handleQueuedTrackingJobs();

    expect(Visitor::sole()->tag)->toBe($tag)
        ->and(VisitorEvent::orderBy('id')->pluck('url')->all())
        ->toBe(['http://localhost/?utm_source=nieuwsbrief', 'http://localhost/test-json']);
});

it('stores no visitor for a client that never sends the cookie back', function () {
    $this->get('/', ['User-Agent' => chromeUserAgent()]);
    $this->get('/', ['User-Agent' => chromeUserAgent()]);

    handleQueuedTrackingJobs();

    expect(Visitor::count())->toBe(0)
        ->and(VisitorEvent::count())->toBe(0);
});

it('adds the landing page to a visitor the application stored in the meantime', function () {
    $tag = $this->get('/', ['User-Agent' => chromeUserAgent()])
        ->getCookie(config('visitor-tracking.cookie_name'), false)
        ->getValue();

    handleQueuedTrackingJobs();

    // For example for a shopping cart, through Visitor::fromRequest()
    Visitor::factory()->create(['tag' => $tag, 'is_bot' => false]);

    $this->withUnencryptedCookie(config('visitor-tracking.cookie_name'), $tag)
        ->get('/test-json', ['User-Agent' => chromeUserAgent()]);

    handleQueuedTrackingJobs();

    expect(VisitorEvent::count())->toBe(2);
});

it('stores the visitor right away for an event other than a page view', function () {
    $request = Request::create('/zoeken', 'GET', server: ['HTTP_USER_AGENT' => chromeUserAgent()]);

    new InsertEventsJob([searchEvent($request)])->handle();

    expect(Visitor::count())->toBe(1)
        ->and(VisitorEvent::sole()->name)->toBe('search');
});

it('gives every event of a request without the cookie the same tag', function () {
    $request = Request::create('/zoeken', 'GET');

    $pageView = new PageViewEvent($request);
    $search = searchEvent($request);

    expect($search->getVisitorTag()->getTag())->toBe($pageView->getVisitorTag()->getTag())
        ->and(Cookie::getQueuedCookies())->toHaveCount(1)
        ->and(Cookie::queued(config('visitor-tracking.cookie_name'))->getValue())->toBe($pageView->getVisitorTag()->getTag());
});

it('ignores bots when configured', function (bool $ignoreBots, int $visitors) {
    Config::set('visitor-tracking.ignore_bots', $ignoreBots);

    $this->withUnencryptedCookie(config('visitor-tracking.cookie_name'), 'anon_crawler:'.time())
        ->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);

    handleQueuedTrackingJobs();

    expect(Visitor::count())->toBe($visitors)
        ->and(VisitorEvent::count())->toBe($visitors);
})->with([
    'ignored' => [true, 0],
    'stored' => [false, 1],
]);
