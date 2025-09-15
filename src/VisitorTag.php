<?php

namespace NiekPH\LaravelVisitorTracking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class VisitorTag
{
    /**
     * Retrieve the visitor tag from the request or generate a new one and queue it as a cookie.
     */
    public function retrieve(Request $request): string
    {
        if (empty($tag = $this->getVisitorTagFromCookies($request))) {
            $tag = $this->generate($request);

            Cookie::queue(
                config('visitor-tracking.cookie_name'),
                $tag,
                config('visitor-tracking.cookie_duration')
            );
        }

        return $tag;
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
        if (! is_null($user = $request->user())) {
            return 'user_'.$user->id;
        }

        return 'anon_'.Str::uuid().'_'.time();
    }
}
