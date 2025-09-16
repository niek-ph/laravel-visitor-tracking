<?php

namespace NiekPH\LaravelVisitorTracking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class EventFactory extends Factory
{
    public function modelName(): string
    {
        return VisitorTracking::$eventModel;
    }

    public function definition(): array
    {
        return [
            'visitor_id' => Visitor::factory(),
            'name' => 'page_view',
            'url' => $this->faker->url(),
            'data' => [],
            'created_at' => $this->faker->dateTime(),
        ];
    }
}
