<?php

namespace NiekPH\LaravelVisitorTracking\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \NiekPH\LaravelVisitorTracking\VisitorTracking
 */
class VisitorTracking extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NiekPH\LaravelVisitorTracking\VisitorTracking::class;
    }
}
