<?php

namespace NiekPH\LaravelVisitorTracking;

use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;

class ClientData
{
    private ?string $ipAddress;

    private ?string $userAgent;

    private array $requestHeaders;

    private bool $isBot = false;

    private ?string $device = null;

    private ?string $browser = null;

    private ?string $platform = null;

    private ?string $platformVersion = null;

    public function __construct(Request $request)
    {
        $this->ipAddress = $request->ip();
        $this->userAgent = $request->userAgent();
        $this->requestHeaders = $_SERVER;
    }

    /**
     * Run the device detector
     */
    public function detectDevice(): DeviceDetector
    {
        $clientHints = config('visitor-tracking.enable_client_hints') ?
            ClientHints::factory($this->requestHeaders)
            :
            null;

        $deviceDetector = new DeviceDetector($this->userAgent, $clientHints);
        $deviceDetector->parse();

        $this->isBot = $deviceDetector->isBot();
        $this->device = $deviceDetector->getDeviceName();
        $this->browser = $deviceDetector->getClient('name');
        $this->platform = $deviceDetector->getOs('name');
        $this->platformVersion = $deviceDetector->getOs('version');

        return $deviceDetector;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function isBot(): bool
    {
        return $this->isBot;
    }

    public function getRequestHeaders(): array
    {
        return $this->requestHeaders;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function getPlatformVersion(): ?string
    {
        return $this->platformVersion;
    }
}
