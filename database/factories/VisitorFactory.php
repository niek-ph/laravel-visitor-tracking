<?php

namespace NiekPH\LaravelVisitorTracking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class VisitorFactory extends Factory
{
    public function modelName(): string
    {
        return VisitorTracking::$visitorModel;
    }

    public function definition(): array
    {
        return [
            'tag' => 'anon_'.Str::uuid().'_'.time(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'user_id' => null,
            'is_bot' => $this->faker->boolean(),
            'device' => $this->faker->macPlatformToken(),
            'browser' => $this->faker->randomElement(['chrome', 'firefox', 'opera', 'safari']),
            'platform' => $this->faker->randomElement(['windows', 'macos', 'android', 'ios', 'linux']),
            'platform_version' => $this->faker->semver(),
            'geo_country' => $this->faker->countryCode(),
            'geo_region' => null,
            'geo_city' => $this->faker->city(),
            'geo_latitude' => $this->faker->latitude(),
            'geo_longitude' => $this->faker->longitude(),
        ];
    }
}
