<?php

namespace NiekPH\LaravelVisitorTracking\Commands;

use Illuminate\Console\Command;

class VisitorTrackingCommand extends Command
{
    public $signature = 'laravel-visitor-tracking';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
