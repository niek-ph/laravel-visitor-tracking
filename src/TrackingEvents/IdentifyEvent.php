<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Links the visitor to an account. Tracked through VisitorTracking::identify().
 */
class IdentifyEvent extends TrackingEvent
{
    public function __construct(Request $request, int|string $userId, array $data = [], ?Carbon $timestamp = null)
    {
        parent::__construct($request, $data, $timestamp);

        $this->setUserId($userId);
    }

    public function getName(): string
    {
        return 'identify';
    }
}
