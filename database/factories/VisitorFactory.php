<?php

namespace NiekPH\LaravelVisitorTracking\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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

        ];
    }
}
