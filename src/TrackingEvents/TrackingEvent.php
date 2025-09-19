<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

use Illuminate\Http\Request;

abstract class TrackingEvent
{
    public string $ipAddress;

    public string $userAgent;

    public int|string|null $userId;

    public ?string $url;

    public array $data = [];

    public function __construct(Request $request, array $data = [])
    {
        $this->ipAddress = $request->ip();
        $this->userAgent = $request->userAgent();
        $this->url = $request->fullUrl();
        $this->userId = $request->user()?->id;
        $this->data = $data;
    }

    abstract public function getName(): string;
}
