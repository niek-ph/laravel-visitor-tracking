<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use NiekPH\LaravelVisitorTracking\ClientData;

beforeEach(function () {
    Config::set('visitor-tracking.enable_geo_ip_lookup', true);
    Config::set('visitor-tracking.enable_client_hints', false);
});

describe('GeoIP Detection', function () {

    it('fetches geoip data successfully with valid response', function () {
        // Mock a successful API response
        Http::fake([
            'api.seeip.org/geoip/*' => Http::response([
                'country_code' => 'US',
                'region' => 'California',
                'city' => 'San Francisco',
                'latitude' => '37.7749',
                'longitude' => '-122.4194',
            ], 200),
        ]);

        // Create a mock request with a fake IP (Google's public DNS)
        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $clientData = new ClientData($request);
        $clientData->detect();

        expect($clientData->getCountryCode())->toBe('US');
        expect($clientData->getRegion())->toBe('California');
        expect($clientData->getCity())->toBe('San Francisco');
        expect($clientData->getLatitude())->toBe('37.7749');
        expect($clientData->getLongitude())->toBe('-122.4194');

        // Verify the correct API endpoint was called
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.seeip.org/geoip/8.8.8.8';
        });
    });

    it('handles partial geoip data correctly', function () {
        Http::fake([
            'api.seeip.org/geoip/*' => Http::response([
                'country_code' => 'GB',
                'city' => 'London',
                // Missing region, latitude, longitude
            ], 200),
        ]);

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        $clientData = new ClientData($request);
        $clientData->detect();

        expect($clientData->getCountryCode())->toBe('GB');
        expect($clientData->getCity())->toBe('London');
        expect($clientData->getRegion())->toBeNull();
        expect($clientData->getLatitude())->toBeNull();
        expect($clientData->getLongitude())->toBeNull();
    });

    it('handles empty geoip response gracefully', function () {
        Http::fake([
            'api.seeip.org/geoip/*' => Http::response([], 200),
        ]);

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $clientData = new ClientData($request);
        $clientData->detect();

        expect($clientData->getCountryCode())->toBeNull();
        expect($clientData->getRegion())->toBeNull();
        expect($clientData->getCity())->toBeNull();
        expect($clientData->getLatitude())->toBeNull();
        expect($clientData->getLongitude())->toBeNull();
    });

    it('handles api timeout gracefully', function () {
        Http::fake([
            'api.seeip.org/geoip/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '203.0.113.1');

        $clientData = new ClientData($request);
        $clientData->detect();

        // Should not throw exception and geo data should be null
        expect($clientData->getCountryCode())->toBeNull();
        expect($clientData->getRegion())->toBeNull();
        expect($clientData->getCity())->toBeNull();
        expect($clientData->getLatitude())->toBeNull();
        expect($clientData->getLongitude())->toBeNull();
    });

    it('handles api error responses gracefully', function () {
        Http::fake([
            'api.seeip.org/geoip/*' => Http::response(['error' => 'Invalid IP'], 400),
        ]);

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', 'invalid-ip');

        $clientData = new ClientData($request);
        $clientData->detect();

        expect($clientData->getCountryCode())->toBeNull();
        expect($clientData->getRegion())->toBeNull();
        expect($clientData->getCity())->toBeNull();
        expect($clientData->getLatitude())->toBeNull();
        expect($clientData->getLongitude())->toBeNull();
    });

    it('skips geoip detection when ip address is empty', function () {
        $request = Request::create('/', 'GET');
        // Don't set REMOTE_ADDR, so IP will be null

        $clientData = new ClientData($request);
        $clientData->detect();

        // Should not make any HTTP requests
        Http::assertNothingSent();

        expect($clientData->getCountryCode())->toBeNull();
        expect($clientData->getRegion())->toBeNull();
        expect($clientData->getCity())->toBeNull();
        expect($clientData->getLatitude())->toBeNull();
        expect($clientData->getLongitude())->toBeNull();
    });

    it('skips geoip detection when geo ip lookup is disabled', function () {
        Config::set('visitor-tracking.enable_geo_ip_lookup', false);

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $clientData = new ClientData($request);
        $clientData->detect();

        // Should not make any HTTP requests
        Http::assertNothingSent();

        expect($clientData->getCountryCode())->toBeNull();
    });

    it('skips geoip detection for bots', function () {
        Http::fake();

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');
        $request->headers->set('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)');

        $clientData = new ClientData($request);
        $clientData->detect();

        expect($clientData->isBot())->toBeTrue();

        // Should not make any HTTP requests for bots
        Http::assertNothingSent();

        expect($clientData->getCountryCode())->toBeNull();
    });

    it('handles various ip address formats', function (string $ipAddress, bool $shouldCallApi) {
        if ($shouldCallApi) {
            Http::fake([
                'api.seeip.org/geoip/*' => Http::response([
                    'country_code' => 'US',
                ], 200),
            ]);
        } else {
            Http::fake();
        }

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', $ipAddress);

        $clientData = new ClientData($request);
        $clientData->detect();

        if ($shouldCallApi) {
            Http::assertSent(function ($request) use ($ipAddress) {
                return str_contains($request->url(), $ipAddress);
            });
            expect($clientData->getCountryCode())->toBe('US');
        } else {
            Http::assertNothingSent();
            expect($clientData->getCountryCode())->toBeNull();
        }
    })->with([
        ['8.8.8.8', true], // Valid IPv4
        ['2001:4860:4860::8888', true], // Valid IPv6
        ['127.0.0.1', true], // Localhost (API will handle this)
        ['::1', true], // IPv6 localhost
    ]);

    it('handles malformed json response gracefully', function () {
        Http::fake([
            'api.seeip.org/geoip/*' => Http::response('invalid json response', 200),
        ]);

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');

        $clientData = new ClientData($request);
        $clientData->detect();

        // Should handle malformed JSON gracefully
        expect($clientData->getCountryCode())->toBeNull();
        expect($clientData->getRegion())->toBeNull();
        expect($clientData->getCity())->toBeNull();
    });

});
