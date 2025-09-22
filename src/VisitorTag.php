<?php

namespace NiekPH\LaravelVisitorTracking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class VisitorTag
{
    private string $tag;

    public function __construct(Request $request)
    {
        $this->tag = $this->retrieve($request);
    }

    /**
     * Retrieve the visitor tag from the request or generate a new one and queue it as a cookie.
     */
    private function retrieve(Request $request): string
    {
        $tag = $this->getVisitorTagFromCookies($request);

        if (empty($tag) || ! $this->isValidTagFormat($tag)) {
            $tag = $this->generate($request);
            $this->queueCookie($tag);

            return $tag;
        }

        if ($this->shouldRenew($tag)) {
            $this->queueCookie($tag);
        }

        return $tag;
    }

    /**
     * Queue the visitor cookie.
     */
    private function queueCookie(string $tag): void
    {
        Cookie::queue(
            config('visitor-tracking.cookie_name'),
            $tag,
            config('visitor-tracking.cookie_duration'),
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

        if (! is_null($user = $request->user())) {
            return 'user_'.$user->id.':'.$time;
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
}
