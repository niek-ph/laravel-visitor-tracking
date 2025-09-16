<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

class PageViewEvent extends TrackingEvent
{
    public function getName(): string
    {
        return 'page_view';
    }
}
