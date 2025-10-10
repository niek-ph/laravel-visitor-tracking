<?php

namespace NiekPH\LaravelVisitorTracking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function handle(): void
    {
        if (empty($this->events)) {
            return;
        }

        $now = now();
        $visitorData = [];
        $visitorTagMap = [];
        $eventData = [];

        foreach ($this->events as $event) {
            $clientData = $event->getClientData()->detect();
            $tag = $event->getVisitorTag()->getTag();

            // Deduplicate visitors within the batch
            if (! isset($visitorTagMap[$tag])) {
                $visitorTagMap[$tag] = true;

                $userAgent = $clientData->getUserAgent();
                $ipAddress = $clientData->getIpAddress();
                $device = $clientData->getDevice();
                $browser = $clientData->getBrowser();
                $platform = $clientData->getPlatform();
                $platformVersion = $clientData->getPlatformVersion();

                $visitorData[] = [
                    'tag' => $tag,
                    'user_id' => $event->getUserId(),
                    'user_agent' => empty($userAgent) ? null : Str::substr($userAgent, 0, 1024),
                    'ip_address' => empty($ipAddress) ? null : Str::substr($ipAddress, 0, 255),
                    'is_bot' => $clientData->isBot(),
                    'device' => empty($device) ? null : Str::substr($device, 0, 255),
                    'browser' => empty($browser) ? null : Str::substr($browser, 0, 255),
                    'platform' => empty($platform) ? null : Str::substr($platform, 0, 255),
                    'platform_version' => empty($platformVersion) ? null : Str::substr($platformVersion, 0, 255),
                    'geo_country' => $clientData->getCountryCode(),
                    'geo_region' => $clientData->getRegion(),
                    'geo_city' => $clientData->getCity(),
                    'geo_latitude' => $clientData->getLatitude(),
                    'geo_longitude' => $clientData->getLongitude(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $url = $event->getUrl();

            $eventData[] = [
                'tag' => $tag,
                'name' => $event->getName(),
                'url' => empty($url) ? null : Str::substr($url, 0, 1024),
                'data' => json_encode($event->getData()),
                'created_at' => $event->getTimestamp(),
            ];
        }

        DB::transaction(function () use ($eventData, $visitorTagMap, $visitorData) {
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

            VisitorTracking::$eventModel::insert($insertEvents);

        });
    }
}
