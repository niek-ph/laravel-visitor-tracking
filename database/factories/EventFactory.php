<?php

namespace NiekPH\LaravelVisitorTracking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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

        ];
    }
}
