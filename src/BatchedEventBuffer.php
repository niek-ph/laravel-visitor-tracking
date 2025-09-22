<?php

namespace NiekPH\LaravelVisitorTracking;

use NiekPH\LaravelVisitorTracking\TrackingEvents\TrackingEvent;

/**
 * This event buffer holds all events that are pushed in a request lifecycle.
 */
class BatchedEventBuffer
{
    private array $events = [];

    public function push(TrackingEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @param  TrackingEvent[]  $events
     */
    public function pushAll(array $events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->events);
    }

    public function getAll(): array
    {
        return $this->events;
    }
}
