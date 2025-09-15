<?php

namespace NiekPH\LaravelVisitorTracking\Middleware;

use Closure;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\Facades\VisitorTracking;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;
use Symfony\Component\HttpFoundation\Response;

readonly class TrackPageView
{
    public function __construct() {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Only track page views for GET requests that return HTML
        if ($this->shouldTrackPageView($request, $response)) {
            VisitorTracking::track(
                $request,
                new PageViewEvent(
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    userId: $request->user()?->id,
                    url: $request->fullUrl(),
                )
            );
        }

        return $response;
    }

    /**
     * Check if the pageview should be tracked
     */
    private function shouldTrackPageView(Request $request, Response $response): bool
    {
        if ($response->isSuccessful()) {
            return false;
        }

        // Skip non get requests
        if (! $request->isMethod('GET')) {
            return false;
        }

        // Handle inertia requests
        if ($request->header('X-Inertia')) {
            // Do not track on partial data loads. (lazy or deferred props)
            return ! $request->hasHeader('X-Inertia-Partial-Data') &&
                ! $request->hasHeader('X-Inertia-Partial-Component') &&
                ! $request->hasHeader('X-Inertia-Partial-Except');
        }

        return $response->headers->get('Content-Type') === 'text/html; charset=UTF-8';
    }
}
