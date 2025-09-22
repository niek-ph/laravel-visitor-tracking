<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

use Carbon\Carbon;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\ClientData;
use NiekPH\LaravelVisitorTracking\VisitorTag;

abstract class TrackingEvent
{
    private mixed $userId;

    private string $url;

    private array $data;

    private Carbon $timestamp;

    private ClientData $clientData;

    private VisitorTag $visitorTag;

    public function __construct(Request $request, array $data = [], ?Carbon $timestamp = null)
    {
        $this->url = $request->fullUrl();
        $this->userId = $request->user()?->id;
        $this->data = $data;
        $this->timestamp = $timestamp ?? now();

        $this->clientData = new ClientData($request);
        $this->visitorTag = new VisitorTag($request);
    }

    abstract public function getName(): string;

    public function getTimestamp(): Carbon
    {
        return $this->timestamp;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getUserId(): int|string|null
    {
        return $this->userId;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getClientData(): ClientData
    {
        return $this->clientData;
    }

    public function getVisitorTag(): VisitorTag
    {
        return $this->visitorTag;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setTimestamp(Carbon $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
