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

        $this->track($request, $response);

        return $response;
    }

    protected function track(Request $request, Response $response): void
    {
        if (! $this->isTrackablePageView($request, $response)) {
            return;
        }

        VisitorTracking::track($request, new PageViewEvent($request));
    }

    protected function isTrackablePageView(Request $request, Response $response): bool
    {
        // Skip on unsuccessful requests
        if (! $response->isSuccessful()) {
            return false;
        }

        // Skip non get requests
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($this->isExcludedRoute($request)) {
            return false;
        }

        // Handle inertia requests
        if ($request->header('X-Inertia')) {
            // Do not track on partial data loads. (lazy or deferred props)
            return ! $this->isInertiaPartialReload($request);
        }

        return $this->isHtmlResponse($response);
    }

    protected function isInertiaPartialReload(Request $request): bool
    {
        return $request->hasHeader('X-Inertia-Partial-Data') ||
             $request->hasHeader('X-Inertia-Partial-Component') ||
             $request->hasHeader('X-Inertia-Partial-Except');
    }

    protected function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }

    protected function isExcludedRoute(Request $request): bool
    {
        $excluded = config('visitor-tracking.excluded_paths', []);

        return collect($excluded)->contains(fn ($path) => $request->is($path));
    }
}
