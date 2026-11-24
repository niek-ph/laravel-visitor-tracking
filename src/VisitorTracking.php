<?php

namespace NiekPH\LaravelVisitorTracking;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\Models\VisitorEvent;
use NiekPH\LaravelVisitorTracking\TrackingEvents\IdentifyEvent;
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
     * Decides per request whether it is tracked. Set once while the application boots.
     *
     * @var (Closure(Request): bool)|null
     */
    protected static ?Closure $trackWhen = null;

    /**
     * Tracks a given event by dispatching a tracking job with relevant data.
     *
     * @param  TrackingEvent[]|TrackingEvent  $event  The event to be tracked.
     */
    public static function track(array|TrackingEvent $event): void
    {
        try {
            if (! static::shouldTrack(App::make('request'))) {
                return;
            }

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
     * Links the visitor of the request to an account, for example after logging in. Stores the visitor
     * right away when it was not stored yet, and records an "identify" event.
     */
    public static function identify(Request $request, int|string $userId, array $data = []): void
    {
        static::track(new IdentifyEvent($request, $userId, $data));
    }

    /**
     * Only track requests for which the callback returns true, for example to skip staff or visitors
     * without consent. Register it once, in a service provider. Pass null to track every request again.
     *
     * @param  (Closure(Request): bool)|null  $callback
     */
    public static function trackWhen(?Closure $callback): void
    {
        static::$trackWhen = $callback;
    }

    /**
     * Whether the request is tracked.
     */
    public static function shouldTrack(Request $request): bool
    {
        if (static::$trackWhen === null) {
            return true;
        }

        return (bool) (static::$trackWhen)($request);
    }

    /**
     * The id of the account that is logged in on the request, from the configured guard and column.
     */
    public static function userIdFromRequest(Request $request): int|string|null
    {
        $user = $request->user(config('visitor-tracking.users.guard'));

        if ($user === null) {
            return null;
        }

        return data_get($user, config('visitor-tracking.users.column') ?? 'id') ?? $user->getAuthIdentifier();
    }

    /**
     * The cache store for pending page views and page view deduplication.
     */
    public static function cache(): Repository
    {
        return Cache::store(config('visitor-tracking.cache_store'));
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
