<?php

namespace NiekPH\LaravelVisitorTracking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class VisitorTag
{
    /**
     * The request attribute that holds the tag, so every event of one request gets the same tag.
     */
    private const string REQUEST_ATTRIBUTE = 'visitor_tracking_tag';

    private string $tag;

    /**
     * Whether the browser sent the tag back in the cookie. True for jobs queued before this was
     * recorded, so those still store their visitor.
     */
    private bool $returning = true;

    public function __construct(Request $request)
    {
        [$this->tag, $this->returning] = $this->resolve($request);
    }

    /**
     * The tag of the request: resolved once per request and then reused.
     *
     * @return array{0: string, 1: bool}
     */
    private function resolve(Request $request): array
    {
        $resolved = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (is_array($resolved)) {
            return $resolved;
        }

        $resolved = $this->retrieve($request);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $resolved);
        $this->storeInContext($resolved[0]);

        return $resolved;
    }

    /**
     * Retrieve the visitor tag from the request or generate a new one and queue it as a cookie.
     *
     * @return array{0: string, 1: bool}
     */
    private function retrieve(Request $request): array
    {
        $tag = $this->getVisitorTagFromCookies($request);

        if (empty($tag) || ! $this->isValidTagFormat($tag)) {
            $tag = $this->generate($request);
            $this->queueCookie($tag);

            return [$tag, false];
        }

        if ($this->shouldRenew($tag)) {
            $this->queueCookie($tag);
        }

        return [$tag, true];
    }

    /**
     * Store the visitor tag in context so the visitor is easily identifiable in logs.
     */
    private function storeInContext(string $tag): void
    {
        Context::add('visitor_tag', $tag);
    }

    /**
     * Queue the visitor cookie. The duration is configured in seconds, the cookie jar takes minutes.
     */
    private function queueCookie(string $tag): void
    {
        Cookie::queue(
            config('visitor-tracking.cookie_name'),
            $tag,
            (int) ceil(config('visitor-tracking.cookie_duration') / 60),
            '',
            '',
            true,
            true
        );
    }

    /**
     * If the cookie should be renewed
     */
    private function shouldRenew(string $tag): bool
    {
        $timestamp = $this->extractTimestampFromTag($tag);

        if (! $timestamp) {
            return false;
        }

        $ageInSeconds = time() - $timestamp;
        $threshold = config('visitor-tracking.cookie_duration') * (2 / 3);

        return $ageInSeconds >= $threshold;
    }

    /**
     * Extract the timestamp from the tag (after ':')
     */
    private function extractTimestampFromTag(string $tag): ?int
    {
        $parts = explode(':', $tag);
        $timestamp = end($parts);

        return is_numeric($timestamp) ? (int) $timestamp : null;
    }

    /**
     * Get the visitor tag from the cookies
     */
    private function getVisitorTagFromCookies(Request $request): string
    {
        return (string) $request->cookie(config('visitor-tracking.cookie_name'), '');
    }

    /**
     * Generate a new visitor tag.
     */
    private function generate(Request $request): string
    {
        $time = time();

        if (! is_null($userId = VisitorTracking::userIdFromRequest($request))) {
            return 'user_'.$userId.':'.$time;
        }

        return 'anon_'.Str::uuid().':'.$time;
    }

    /**
     * Validate the visitor tag format.
     * Expected format: prefix (user_ or anon_) + identifier + ':' + timestamp
     */
    private function isValidTagFormat(string $tag): bool
    {
        if (! str_contains($tag, ':')) {
            return false;
        }

        [$prefixPart, $timestampPart] = explode(':', $tag, 2);

        if (empty($prefixPart) || ! is_numeric($timestampPart)) {
            return false;
        }

        if ($timestampPart > time()) {
            return false;
        }

        if (! Str::startsWith($prefixPart, ['user_', 'anon_'])) {
            return false;
        }

        return true;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * Whether the browser sent the visitor cookie back, so it is not a first request without the cookie.
     */
    public function isReturning(): bool
    {
        return $this->returning;
    }
}
