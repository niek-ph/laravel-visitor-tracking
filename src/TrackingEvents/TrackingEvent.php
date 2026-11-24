<?php

namespace NiekPH\LaravelVisitorTracking\TrackingEvents;

use Carbon\Carbon;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\ClientData;
use NiekPH\LaravelVisitorTracking\VisitorTag;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

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
        $this->url = $this->urlFrom($request);
        $this->userId = VisitorTracking::userIdFromRequest($request);
        $this->data = $data;
        $this->timestamp = $timestamp ?? now();

        $this->clientData = new ClientData($request);
        $this->visitorTag = new VisitorTag($request);
    }

    abstract public function getName(): string;

    /**
     * Whether this event stores a visitor that has not sent the visitor cookie back yet. Page views do
     * not, so clients that never keep cookies do not create visitors (see `require_returning_visitor`).
     */
    public function createsVisitor(): bool
    {
        return true;
    }

    /**
     * The url of the request in the configured format.
     */
    private function urlFrom(Request $request): string
    {
        return match (config('visitor-tracking.url_format', 'full')) {
            'path' => $request->getPathInfo(),
            'path_with_query' => $request->getPathInfo().(($query = $request->getQueryString()) !== null ? '?'.$query : ''),
            default => $request->fullUrl(),
        };
    }

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

    public function setUserId(int|string|null $userId): void
    {
        $this->userId = $userId;
    }
}
