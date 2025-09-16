<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

use Illuminate\Http\Request;

abstract class TrackingEvent
{
    public string $ipAddress {
        get {
            return $this->ipAddress;
        }
    }

    public string $userAgent {
        get {
            return $this->userAgent;
        }
    }

    public int|string|null $userId {
        get {
            return $this->userId;
        }
    }

    public ?string $url {
        get {
            return $this->url;
        }
    }

    public array $data = [] {
        get {
            return $this->data;
        }
    }

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
