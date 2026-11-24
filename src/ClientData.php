<?php

namespace NiekPH\LaravelVisitorTracking;

use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClientData
{
    private ?string $ipAddress;

    private ?string $userAgent;

    /**
     * @var array<string, string>
     */
    private array $requestHeaders;

    private bool $isBot = false;

    private ?string $device = null;

    private ?string $browser = null;

    private ?string $platform = null;

    private ?string $platformVersion = null;

    private ?string $countryCode = null;

    private ?string $region = null;

    private ?string $city = null;

    private ?string $latitude = null;

    private ?string $longitude = null;

    public function __construct(Request $request)
    {
        $this->ipAddress = $request->ip();
        $this->userAgent = $request->userAgent();
        $this->requestHeaders = $this->clientHintHeaders($request);
    }

    /**
     * The request headers that the device detector's client hints read: the Sec-CH-UA headers
     * and X-Requested-With. Nothing else is kept, because this object is serialized into the
     * queued job, and the server variables also hold the environment's secrets.
     *
     * @return array<string, string>
     */
    private function clientHintHeaders(Request $request): array
    {
        $headers = [];

        foreach (array_keys($request->headers->all()) as $name) {
            if (str_starts_with($name, 'sec-ch-ua') || $name === 'x-requested-with') {
                $headers[$name] = (string) $request->headers->get($name);
            }
        }

        return $headers;
    }

    /**
     * Run the device detector
     */
    public function detect(): self
    {
        $clientHints = config('visitor-tracking.enable_client_hints') ?
            ClientHints::factory($this->requestHeaders)
            :
            null;

        $deviceDetector = new DeviceDetector($this->userAgent, $clientHints);
        $deviceDetector->parse();

        $deviceName = $deviceDetector->getDeviceName();
        $browser = $deviceDetector->getClient('name');
        $platform = $deviceDetector->getOs('name');
        $platformVersion = $deviceDetector->getOs('version');

        $isBot = $deviceDetector->isBot();

        $this->isBot = $isBot;
        $this->device = $this->isEmptyOrUnknown($deviceName) ? null : $deviceName;
        $this->browser = $this->isEmptyOrUnknown($browser) ? null : $browser;
        $this->platform = $this->isEmptyOrUnknown($platform) ? null : $platform;
        $this->platformVersion = $this->isEmptyOrUnknown($platformVersion) ? null : $platformVersion;

        if (! $isBot && config('visitor-tracking.enable_geo_ip_lookup')) {
            $this->detectGeoIp();
        }

        return $this;
    }

    /**
     * Detects GeoIP information for the current IP address.
     *
     * Makes an HTTP request to a GeoIP API to retrieve geographic location data
     * for the IP address.
     *
     *
     * @see https://seeip.org for more information
     */
    private function detectGeoIp(): void
    {
        if (empty($this->ipAddress)) {
            return;
        }

        try {
            $response = Http::timeout(2)
                ->get("https://api.seeip.org/geoip/$this->ipAddress")
                ->throw();

            if ($response->successful() && ! empty($json = $response->json())) {
                $this->setGeoIpData($json);
            }

        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function setGeoIpData(array $json): void
    {
        if (! empty($json['country_code'])) {
            $this->countryCode = $json['country_code'];
        }

        if (! empty($json['region'])) {
            $this->region = $json['region'];
        }

        if (! empty($json['city'])) {
            $this->city = $json['city'];
        }

        if (! empty($json['latitude'])) {
            $this->latitude = $json['latitude'];
        }

        if (! empty($json['longitude'])) {
            $this->longitude = $json['longitude'];
        }
    }

    private function isEmptyOrUnknown(?string $value): bool
    {
        return empty($value) || $value === DeviceDetector::UNKNOWN;
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

    /**
     * The client hint headers of the request, keyed by lowercase header name.
     *
     * @return array<string, string>
     */
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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }
}
