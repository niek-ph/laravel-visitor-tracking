<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

abstract class TrackingEvent
{
    public function __construct(
        public string $name,
        public string $ipAddress,
        public string $userAgent,
        public ?string $userId = null,
        public ?string $url = null,
        public array $data = [],
    ) {}

}
