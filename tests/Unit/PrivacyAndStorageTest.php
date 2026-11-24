<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use NiekPH\LaravelVisitorTracking\ClientData;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\Models\VisitorEvent;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;

uses(RefreshDatabase::class);

it('anonymizes the ip address', function (string $ipAddress, string $anonymized) {
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ipAddress]);

    expect(new ClientData($request)->getIpAddress())->toBe($anonymized);
})->with([
    'IPv4 to a /24 network' => ['203.0.113.195', '203.0.113.0'],
    'IPv6 to a /48 network' => ['2001:db8:85a3:8d3:1319:8a2e:370:7348', '2001:db8:85a3::'],
]);

it('does not look up the location by default', function () {
    Http::fake();

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.195']);
    new ClientData($request)->detect();

    Http::assertNothingSent();
});

it('looks up the device and location once per visitor, with the anonymized ip address', function () {
    Config::set('visitor-tracking.enable_geo_ip_lookup', true);
    Http::fake(['api.seeip.org/*' => Http::response(['country_code' => 'NL'])]);

    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '203.0.113.195',
        'HTTP_USER_AGENT' => chromeUserAgent(),
    ]);
    $request->cookies->set(config('visitor-tracking.cookie_name'), 'anon_returning:'.time());

    new InsertEventsJob([new PageViewEvent($request), new PageViewEvent($request), new PageViewEvent($request)])->handle();
    new InsertEventsJob([new PageViewEvent($request)])->handle();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.seeip.org/geoip/203.0.113.0');

    expect(Visitor::sole()->geo_country)->toBe('NL')
        ->and(VisitorEvent::count())->toBe(4);
});

it('stores the url in the configured format', function (string $format, string $url) {
    Config::set('visitor-tracking.url_format', $format);

    $event = new PageViewEvent(Request::create('https://shop.test/jurken/zomer?maat=98&kleur=roze'));

    expect($event->getUrl())->toBe($url);
})->with([
    'full' => ['full', 'https://shop.test/jurken/zomer?kleur=roze&maat=98'],
    'path with query' => ['path_with_query', '/jurken/zomer?kleur=roze&maat=98'],
    'path' => ['path', '/jurken/zomer'],
]);

it('indexes the visitor and user foreign keys', function () {
    expect(Schema::hasIndex('visitor_events', ['visitor_id']))->toBeTrue()
        ->and(Schema::hasIndex('visitors', ['user_id']))->toBeTrue();
});

it('links user_id to the configured users table', function () {
    Config::set('visitor-tracking.users.table', 'customers');

    $migration = include __DIR__.'/../../database/migrations/create_visitor_tracking_tables.php.stub';
    $migration->down();
    $migration->up();

    expect(collect(Schema::getForeignKeys('visitors'))->pluck('foreign_table')->all())->toBe(['customers']);
});

it('takes the country from the proxy header', function (?string $headerValue, ?string $country) {
    $request = Request::create('/', 'GET');

    if ($headerValue !== null) {
        $request->headers->set('CF-IPCountry', $headerValue);
    }

    expect(new ClientData($request)->getCountryCode())->toBe($country);
})->with([
    'country' => ['nl', 'NL'],
    'unknown' => ['XX', null],
    'Tor network' => ['T1', null],
    'invalid' => ['<b>NL</b>', null],
    'missing' => [null, null],
]);

it('stores the country from the proxy header without a lookup', function () {
    Http::fake();

    $request = Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => chromeUserAgent(),
        'HTTP_CF_IPCOUNTRY' => 'BE',
    ]);
    $request->cookies->set(config('visitor-tracking.cookie_name'), 'anon_returning:'.time());

    new InsertEventsJob([new PageViewEvent($request)])->handle();

    Http::assertNothingSent();
    expect(Visitor::sole()->geo_country)->toBe('BE');
});

it('takes the ip address from the configured proxy header', function (?string $header, string $headerValue, string $ipAddress) {
    Config::set('visitor-tracking.anonymize_ip_address', false);
    Config::set('visitor-tracking.ip_address_header', $header);

    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_CF_CONNECTING_IP' => $headerValue,
    ]);

    expect(new ClientData($request)->getIpAddress())->toBe($ipAddress);
})->with([
    'configured' => ['CF-Connecting-IP', '203.0.113.195', '203.0.113.195'],
    'not configured' => [null, '203.0.113.195', '10.0.0.1'],
    'invalid header value' => ['CF-Connecting-IP', 'not-an-ip', '10.0.0.1'],
]);
