<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

class PageViewEvent extends TrackingEvent
{
    public function __construct(
        string $ipAddress,
        string $userAgent,
        ?string $userId = null,
        ?string $url = null,
        array $data = []
    ) {
        parent::__construct( 'page_view', $ipAddress, $userAgent, $userId, $url, $data);
    }
}
