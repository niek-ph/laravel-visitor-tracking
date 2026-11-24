<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;

beforeEach(function () {
    Config::set('visitor-tracking.enable_geo_ip_lookup', false);
    Config::set('visitor-tracking.enable_client_hints', true);
});

it('passes the client hints to the device detector after the job is queued', function () {
    // Chrome reports Windows 11 as "Windows NT 10.0"; only the platform version hint tells them apart.
    $request = Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'HTTP_SEC_CH_UA_PLATFORM' => '"Windows"',
        'HTTP_SEC_CH_UA_PLATFORM_VERSION' => '"15.0.0"',
    ]);

    $job = unserialize(serialize(new InsertEventsJob([new PageViewEvent($request)])));
    $clientData = $job->events[0]->getClientData()->detect();

    expect($clientData->getRequestHeaders())->toBe([
        'sec-ch-ua-platform' => '"Windows"',
        'sec-ch-ua-platform-version' => '"15.0.0"',
    ])
        ->and($clientData->getPlatform())->toBe('Windows')
        ->and($clientData->getPlatformVersion())->toBe('11');
});
