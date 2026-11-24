<?php

namespace NiekPH\LaravelVisitorTracking\Middleware;

use Closure;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\Facades\VisitorTracking;
use NiekPH\LaravelVisitorTracking\TrackingEvents\PageViewEvent;
use NiekPH\LaravelVisitorTracking\VisitorTag;
use NiekPH\LaravelVisitorTracking\VisitorTracking as Tracking;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    private bool $tracked = false;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
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
        // Skip non get requests, HEAD requests included
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($this->isPrefetch($request)) {
            return false;
        }

        if ($this->isExcludedRoute($request)) {
            return false;
        }

        // Do not track on Inertia partial data loads (lazy or deferred props)
        if ($request->header('X-Inertia') && $this->isInertiaPartialReload($request)) {
            return false;
        }

        if (! Tracking::shouldTrack($request)) {
            return false;
        }

        return ! $this->isRepeatedPageView($request);
    }

    /**
     * Whether the browser fetches the page ahead of a possible visit: Inertia and link prefetching,
     * Chrome's speculation rules (prefetch and prerender) and Safari's previews.
     */
    protected function isPrefetch(Request $request): bool
    {
        $purpose = strtolower(implode(' ', [
            $request->header('Purpose', ''),
            $request->header('Sec-Purpose', ''),
            $request->header('X-Purpose', ''),
            $request->header('X-Moz', ''),
        ]));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview');
    }

    /**
     * Whether the visitor viewed the same url within `deduplicate_page_views_for` seconds.
     */
    protected function isRepeatedPageView(Request $request): bool
    {
        $seconds = (int) config('visitor-tracking.deduplicate_page_views_for');

        if ($seconds <= 0) {
            return false;
        }

        $tag = new VisitorTag($request)->getTag();
        $key = 'visitor-tracking:page-view:'.sha1($tag.'|'.$request->getRequestUri());

        return ! Tracking::cache()->add($key, true, $seconds);
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
