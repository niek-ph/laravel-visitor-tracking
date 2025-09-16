<?php

use NiekPH\LaravelVisitorTracking\VisitorTag;

it('retrieves existing visitor tag from cookies', function () {
    $request = Request::create('/');
    $request->cookies->set(config('visitor-tracking.cookie_name'), 'existing_tag_123');

    $visitorTag = new VisitorTag;
    $tag = $visitorTag->retrieve($request);

    expect($tag)->toBe('existing_tag_123');
});

it('generates new visitor tag when cookie is empty', function () {
    $request = Request::create('/');

    $visitorTag = new VisitorTag;
    $tag = $visitorTag->retrieve($request);

    expect($tag)->toStartWith('anon_');
});

it('generates user-based tag for authenticated users', function () {
    $user = new \Illuminate\Foundation\Auth\User;
    $user->id = 123;

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $visitorTag = new VisitorTag;
    $tag = $visitorTag->retrieve($request);

    expect($tag)->toBe('user_123');
});

it('generates anonymous tag for unauthenticated users', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn () => null);

    $visitorTag = new VisitorTag;
    $tag = $visitorTag->retrieve($request);

    expect($tag)->toStartWith('anon_');
});

it('uses custom cookie name from config', function () {
    Config::set('visitor-tracking.cookie_name', 'custom_visitor_tag');

    $request = Request::create('/');
    $request->cookies->set('custom_visitor_tag', 'custom_tag_456');

    $visitorTag = new VisitorTag;
    $tag = $visitorTag->retrieve($request);

    expect($tag)->toBe('custom_tag_456');
});
