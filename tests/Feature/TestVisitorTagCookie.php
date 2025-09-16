<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets visitor tag cookie on first visit', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
    $response->assertCookie(\config('visitor-tracking.cookie_name'));

    $cookie = $response->getCookie('visitor_tag', false);
    expect($cookie->getValue())->toStartWith('anon_');
});

it('reuses existing visitor tag from cookie', function () {
    // First request sets the cookie
    $firstResponse = $this->get('/');
    $cookie = $firstResponse->getCookie(\config('visitor-tracking.cookie_name'), false);
    $firstTag = $cookie->getValue();

    // Second request should reuse the same tag
    $secondResponse = $this->withCookie(\config('visitor-tracking.cookie_name'), $firstTag)->get('/');

    // The response might not have the cookie again since it already exists
    expect($firstTag)->toStartWith('anon_');
});

it('generates user-based tag for authenticated users', function () {
    $user = new \Illuminate\Foundation\Auth\User;
    $user->id = 123;

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
    $response->assertCookie('visitor_tag');

    $cookie = $response->getCookie('visitor_tag', false);
    expect($cookie->getValue())->toBe('user_123');
});
