<?php

namespace NiekPH\LaravelVisitorTracking\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NiekPH\LaravelVisitorTracking\ClientData;
use NiekPH\LaravelVisitorTracking\TrackingEvents\TrackingEvent;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class InsertEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<TrackingEvent>  $events
     */
    public function __construct(
        public array $events = []
    ) {
        if (! is_null($connection = config('visitor-tracking.queue.connection'))) {
            $this->onConnection($connection);
        }

        if (! is_null($queue = config('visitor-tracking.queue.name'))) {
            $this->onQueue($queue);
        }
    }

    /**
     * Store the events. A known visitor only gets its events, its last seen time and, when the events
     * carry one, its account; its device and location are detected once, when it is first stored.
     */
    public function handle(): void
    {
        if (empty($this->events)) {
            return;
        }

        $now = now();

        /** @var array<string, list<TrackingEvent>> $eventsByTag */
        $eventsByTag = [];

        foreach ($this->events as $event) {
            $eventsByTag[$event->getVisitorTag()->getTag()][] = $event;
        }

        /** @var Collection<string, Model> $existingVisitors */
        $existingVisitors = VisitorTracking::$visitorModel::query()
            ->whereIn('tag', array_keys($eventsByTag))
            ->get(['id', 'tag', 'user_id', 'is_bot', 'created_at'])
            ->keyBy('tag');

        $newVisitors = [];
        $eventRows = [];

        foreach ($eventsByTag as $tag => $events) {
            $rows = array_map($this->eventRow(...), $events);
            $visitor = $existingVisitors->get($tag);

            if ($visitor !== null) {
                if ($visitor->getAttribute('is_bot') && config('visitor-tracking.ignore_bots')) {
                    continue;
                }

                $eventRows[$tag] = $this->isNew($visitor, $now) ? [...$this->pullPendingPageViews($tag), ...$rows] : $rows;

                continue;
            }

            $clientData = $events[0]->getClientData()->detectDevice();

            if ($clientData->isBot() && config('visitor-tracking.ignore_bots')) {
                continue;
            }

            if (! $this->createsVisitor($events)) {
                $this->holdPendingPageViews($tag, $rows);

                continue;
            }

            $newVisitors[$tag] = $this->visitorRow($tag, $events, $clientData->detectLocation(), $now);
            $eventRows[$tag] = [...$this->pullPendingPageViews($tag), ...$rows];
        }

        if (empty($eventRows)) {
            return;
        }

        VisitorTracking::$visitorModel::query()->getConnection()->transaction(
            fn () => $this->store($eventsByTag, $existingVisitors, $newVisitors, $eventRows)
        );
    }

    /**
     * @param  array<string, list<TrackingEvent>>  $eventsByTag
     * @param  Collection<string, Model>  $existingVisitors
     * @param  array<string, array<string, mixed>>  $newVisitors
     * @param  array<string, list<array<string, mixed>>>  $eventRows
     */
    private function store(array $eventsByTag, Collection $existingVisitors, array $newVisitors, array $eventRows): void
    {
        $visitorModel = VisitorTracking::$visitorModel;
        $visitorIds = $existingVisitors->map(fn (Model $visitor) => $visitor->getKey())->all();

        if (! empty($newVisitors)) {
            $visitorModel::query()->insertOrIgnore(array_values($newVisitors));

            $visitorIds += $visitorModel::query()
                ->whereIn('tag', array_keys($newVisitors))
                ->pluck('id', 'tag')
                ->all();
        }

        $seenVisitorIds = [];

        foreach ($existingVisitors as $tag => $visitor) {
            if (! isset($eventRows[$tag])) {
                continue;
            }

            $userId = $this->userIdOf($eventsByTag[$tag]);

            if ($userId !== null && (string) $visitor->getAttribute('user_id') !== (string) $userId) {
                $visitorModel::query()->whereKey($visitor->getKey())->update(['user_id' => $userId]);

                continue;
            }

            $seenVisitorIds[] = $visitor->getKey();
        }

        if (! empty($seenVisitorIds)) {
            $visitorModel::query()->whereKey($seenVisitorIds)->touch();
        }

        $insertEvents = [];

        foreach ($eventRows as $tag => $rows) {
            if (! isset($visitorIds[$tag])) {
                continue;
            }

            foreach ($rows as $row) {
                $insertEvents[] = $this->withNewId(['visitor_id' => $visitorIds[$tag], ...$row]);
            }
        }

        if (! empty($insertEvents)) {
            VisitorTracking::$eventModel::query()->insert($insertEvents);
        }
    }

    /**
     * Whether the events may store a visitor that is not stored yet: when the browser sent the cookie
     * back, or when one of the events is more than a page view.
     *
     * @param  list<TrackingEvent>  $events
     */
    private function createsVisitor(array $events): bool
    {
        if (! config('visitor-tracking.require_returning_visitor')) {
            return true;
        }

        foreach ($events as $event) {
            if ($event->getVisitorTag()->isReturning() || $event->createsVisitor()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hold the page views of a first request without the cookie until the visitor returns.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function holdPendingPageViews(string $tag, array $rows): void
    {
        $ttl = (int) config('visitor-tracking.pending_page_view_ttl');

        if ($ttl > 0) {
            VisitorTracking::cache()->put($this->pendingKey($tag), $rows, $ttl);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pullPendingPageViews(string $tag): array
    {
        if (! config('visitor-tracking.require_returning_visitor')) {
            return [];
        }

        $rows = VisitorTracking::cache()->pull($this->pendingKey($tag));

        return is_array($rows) ? $rows : [];
    }

    private function pendingKey(string $tag): string
    {
        return 'visitor-tracking:pending-page-views:'.$tag;
    }

    /**
     * Whether the visitor was stored so recently that page views of its first request may still be pending,
     * for example when the application stored it before this job ran.
     */
    private function isNew(Model $visitor, CarbonInterface $now): bool
    {
        $createdAt = $visitor->getAttribute('created_at');

        return $createdAt instanceof CarbonInterface
            && $createdAt->gte($now->copy()->subSeconds((int) config('visitor-tracking.pending_page_view_ttl')));
    }

    /**
     * The last account id in the events. Anonymous events never unlink a visitor.
     *
     * @param  list<TrackingEvent>  $events
     */
    private function userIdOf(array $events): int|string|null
    {
        $userId = null;

        foreach ($events as $event) {
            $userId = $event->getUserId() ?? $userId;
        }

        return $userId;
    }

    /**
     * @param  list<TrackingEvent>  $events
     * @return array<string, mixed>
     */
    private function visitorRow(string $tag, array $events, ClientData $clientData, CarbonInterface $now): array
    {
        return $this->withNewId([
            'tag' => $tag,
            'user_id' => $this->userIdOf($events),
            'user_agent' => $this->limit($clientData->getUserAgent(), 1024),
            'ip_address' => $this->limit($clientData->getIpAddress()),
            'is_bot' => $clientData->isBot(),
            'device' => $this->limit($clientData->getDevice()),
            'browser' => $this->limit($clientData->getBrowser()),
            'platform' => $this->limit($clientData->getPlatform()),
            'platform_version' => $this->limit($clientData->getPlatformVersion()),
            'geo_country' => $clientData->getCountryCode(),
            'geo_region' => $clientData->getRegion(),
            'geo_city' => $clientData->getCity(),
            'geo_latitude' => $clientData->getLatitude(),
            'geo_longitude' => $clientData->getLongitude(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Add a generated primary key when `database.id_type` is uuid or ulid.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withNewId(array $row): array
    {
        $id = VisitorTracking::newId();

        return $id === null ? $row : ['id' => $id, ...$row];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRow(TrackingEvent $event): array
    {
        return [
            'name' => $event->getName(),
            'url' => $this->limit($event->getUrl(), 1024),
            'data' => json_encode($event->getData()),
            'created_at' => $event->getTimestamp(),
        ];
    }

    private function limit(?string $value, int $length = 255): ?string
    {
        return empty($value) ? null : Str::substr($value, 0, $length);
    }
}
