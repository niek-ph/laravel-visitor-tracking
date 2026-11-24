<?php

use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function chromeUserAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
}

/**
 * Handle the tracking jobs pushed so far, in order, and start a fresh fake queue for the next ones.
 */
function handleQueuedTrackingJobs(): void
{
    $jobs = Queue::pushed(InsertEventsJob::class);

    Queue::fake();

    $jobs->each->handle();
}
