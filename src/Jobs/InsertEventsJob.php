<?php

namespace NiekPH\LaravelVisitorTracking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        if (! is_null($connection = config('visitor-tracking.queue_connection'))) {
            $this->onConnection($connection);
        }

        if (! is_null($queue = config('visitor-tracking.queue_name'))) {
            $this->onQueue($queue);
        }
    }

    public function handle(): void
    {
        if (empty($this->events)) {
            return;
        }

        DB::transaction(function () {
            $now = now();
            $visitorData = [];
            $visitorTagMap = [];
            $eventData = [];

            foreach ($this->events as $event) {
                $event->getClientData()->detectDevice();
                $tag = $event->getVisitorTag()->getTag();

                // Deduplicate visitors within the batch
                if (! isset($visitorTagMap[$tag])) {
                    $visitorTagMap[$tag] = true;
                    $visitorData[] = [
                        'tag' => $tag,
                        'user_id' => $event->getUserId(),
                        'user_agent' => $event->getClientData()->getUserAgent(),
                        'ip_address' => $event->getClientData()->getIpAddress(),
                        'is_bot' => $event->getClientData()->isBot(),
                        'device' => $event->getClientData()->getDevice(),
                        'browser' => $event->getClientData()->getBrowser(),
                        'platform' => $event->getClientData()->getPlatform(),
                        'platform_version' => $event->getClientData()->getPlatformVersion(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $eventData[] = [
                    'tag' => $tag,
                    'name' => $event->getName(),
                    'url' => $event->getUrl(),
                    'data' => json_encode($event->getData()),
                    'created_at' => $event->getTimestamp(),
                ];
            }

            if (! empty($visitorData)) {
                VisitorTracking::$visitorModel::upsert(
                    $visitorData,
                    ['tag'],
                    [
                        'user_id',
                        'user_agent',
                        'ip_address',
                        'is_bot',
                        'device',
                        'browser',
                        'platform',
                        'platform_version',
                        'updated_at',
                    ]
                );
            }

            $visitorTags = array_keys($visitorTagMap);
            $visitors = VisitorTracking::$visitorModel::whereIn('tag', $visitorTags)
                ->pluck('id', 'tag')
                ->toArray();

            $insertEvents = [];
            foreach ($eventData as $event) {
                $insertEvents[] = [
                    'visitor_id' => $visitors[$event['tag']],
                    'name' => $event['name'],
                    'url' => $event['url'],
                    'data' => $event['data'],
                    'created_at' => $event['created_at'],
                ];
            }

            if (! empty($insertEvents)) {
                VisitorTracking::$eventModel::insert($insertEvents);
            }
        });
    }
}
