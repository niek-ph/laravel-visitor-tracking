<?php

namespace NiekPH\LaravelVisitorTracking\Middleware;

use Closure;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\Facades\VisitorTracking;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    private bool $tracked = false;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->track($request);

        return $next($request);
    }

    protected function track(Request $request): void
    {
        if (! $this->isTrackablePageView($request)) {
            return;
        }

        VisitorTracking::track(new PageViewEvent($request));

        $this->tracked = true;
    }

    protected function isTrackablePageView(Request $request): bool
    {
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

        return true;
    }

    protected function isInertiaPartialReload(Request $request): bool
    {
        return $request->hasHeader('X-Inertia-Partial-Data') ||
             $request->hasHeader('X-Inertia-Partial-Component') ||
             $request->hasHeader('X-Inertia-Partial-Except');
    }

    protected function isExcludedRoute(Request $request): bool
    {
        $excluded = config('visitor-tracking.excluded_paths', []);

        return collect($excluded)->contains(fn ($path) => $request->is($path));
    }

    /**
     * Whether a page view has been tracked
     */
    protected function isTracked(): bool
    {
        return $this->tracked;
    }
}
