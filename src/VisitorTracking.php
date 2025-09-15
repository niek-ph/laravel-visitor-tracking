<?php

namespace NiekPH\LaravelVisitorTracking;


use DeviceDetector\ClientHints;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\TrackingEvents\TrackingEvent;
use NiekPH\LaravelVisitorTracking\Jobs\TrackEventJob;
use NiekPH\LaravelVisitorTracking\Models\Event;
use NiekPH\LaravelVisitorTracking\Models\Visitor;

class VisitorTracking
{
    /**
     * The visitor model class name.
     *
     * @var string
     */
    public static string $visitorModel = Visitor::class;

    /**
     * The event model class name.
     *
     * @var string
     */
    public static string $eventModel = Event::class;

    /**
     * Tracks a given event by dispatching a tracking job with relevant data.
     *
     * @param  Request  $request  The incoming HTTP request containing necessary data for tracking.
     * @param  TrackingEvent  $event  The event to be tracked.
     * @return void
     */
    public function track(Request $request, TrackingEvent $event): void
    {
        $visitorTag =  new VisitorTag()->retrieve($request);
        $clientHints = config('visitor-tracking.enable_client_hints') ? ClientHints::factory($request->headers->all()) : null;

        TrackEventJob::dispatch($visitorTag, $event, now(), $clientHints);
    }

    /**
     * Set the visitor model class name.
     *
     * @param  string  $visitorModel
     * @return void
     */
    public static function useVisitorModel(string $visitorModel): void
    {
        static::$visitorModel = $visitorModel;
    }

    /**
     * Set the event mode class name.
     *
     * @param  string  $eventModel
     * @return void
     */
    public static function useEventModel(string $eventModel): void
    {
        static::$eventModel = $eventModel;
    }

}
