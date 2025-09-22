<?php

namespace NiekPH\LaravelVisitorTracking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\Models\VisitorEvent;
use NiekPH\LaravelVisitorTracking\TrackingEvents\TrackingEvent;

class VisitorTracking
{
    /**
     * The visitor model class name.
     */
    public static string $visitorModel = Visitor::class;

    /**
     * The event model class name.
     */
    public static string $eventModel = VisitorEvent::class;

    /**
     * Tracks a given event by dispatching a tracking job with relevant data.
     *
     * @param  TrackingEvent[]|TrackingEvent  $event  The event to be tracked.
     */
    public static function track(array|TrackingEvent $event): void
    {
        try {
            $buffer = App::make(BatchedEventBuffer::class);

            if (is_array($event)) {
                $buffer->pushAll($event);
            } else {
                $buffer->push($event);
            }

        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Get the visitor from the request.
     */
    public function getVisitorFromRequest(Request $request): Visitor
    {
        return static::$visitorModel::fromRequest($request);
    }

    /**
     * Set the visitor model class name.
     */
    public static function useVisitorModel(string $visitorModel): void
    {
        static::$visitorModel = $visitorModel;
    }

    /**
     * Set the event mode class name.
     */
    public static function useEventModel(string $eventModel): void
    {
        static::$eventModel = $eventModel;
    }
}
