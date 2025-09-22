<?php

use NiekPH\LaravelVisitorTracking\VisitorTag;

it('retrieves existing visitor tag from cookies', function () {
    $time = time();
    $request = Request::create('/');
    $request->cookies->set(config('visitor-tracking.cookie_name'), "anon_existing_tag_123:$time");

    $visitorTag = new VisitorTag($request);

    expect($visitorTag->getTag())->toBe("anon_existing_tag_123:$time");
});

it('generates new visitor tag when cookie is empty', function () {
    $request = Request::create('/');

    $visitorTag = new VisitorTag($request);

    expect($visitorTag->getTag())->toStartWith('anon_');
});

it('generates user-based tag for authenticated users', function () {
    $user = new \Illuminate\Foundation\Auth\User;
    $user->id = 123;

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $visitorTag = new VisitorTag($request);

    expect($visitorTag->getTag())->toStartWith('user_123');
});

it('generates anonymous tag for unauthenticated users', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn () => null);

    $visitorTag = new VisitorTag($request);

    expect($visitorTag->getTag())->toStartWith('anon_');
});

it('uses custom cookie name from config', function () {
    Config::set('visitor-tracking.cookie_name', 'custom_visitor_tag');

    $request = Request::create('/');
    $time = time();
    $request->cookies->set('custom_visitor_tag', "anon_456:$time");

    $visitorTag = new VisitorTag($request);

    expect($visitorTag->getTag())->toBe("anon_456:$time");
});
