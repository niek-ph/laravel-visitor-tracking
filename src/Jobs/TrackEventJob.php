<?php

namespace NiekPH\LaravelVisitorTracking\Jobs;

use Carbon\Carbon;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NiekPH\LaravelVisitorTracking\TrackingEvents\TrackingEvent;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class TrackEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $visitorTag,
        public readonly TrackingEvent $event,
        public readonly Carbon $timestamp,
        public readonly ?ClientHints $clientHints = null,
    ) {}

    public function handle(): void
    {
        $deviceDetector = new DeviceDetector($this->event->userAgent, $this->clientHints);
        $deviceDetector->parse();

        $visitor = VisitorTracking::$visitorModel->updateOrCreate(
            ['tag' => $this->visitorTag],
            [
                'tag' => $this->visitorTag,
                'user_agent' => $this->event->userAgent,
                'ip_address' => $this->event->ipAddress,
                'is_bot' => $deviceDetector->isBot(),
                'device' => $deviceDetector->getDeviceName(),
                'browser' => $deviceDetector->getClient('name'),
                'platform' => $deviceDetector->getOs('name'),
                'platform_version' => $deviceDetector->getOs('version'),
            ]
        );

        $visitor->events()->create([
            'name' => $this->event->name,
            'url' => $this->event->url,
            'data' => $this->event->data ?? [],
            'created_at' => $this->timestamp,
        ]);
    }
}
