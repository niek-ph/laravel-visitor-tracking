<?php

namespace NiekPH\LaravelVisitorTracking;

use Illuminate\Support\Facades\App;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VisitorTrackingServiceProvider extends PackageServiceProvider
{
    public function bootingPackage(): void
    {
        $models = $this->app['config']->get('visitor-tracking.models');

        VisitorTracking::useVisitorModel($models['visitor']);
        VisitorTracking::useEventModel($models['visitor_event']);

        $this->app->terminating(fn () => $this->dispatchEvents());
    }

    public function configurePackage(Package $package): void
    {
        $this->app->scoped(BatchedEventBuffer::class, fn () => new BatchedEventBuffer);

        $package
            ->name('laravel-visitor-tracking')
            ->hasConfigFile()
            ->hasMigration('create_visitor_tracking_tables')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishAssets()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('niek-ph/laravel-visitor-tracking');
            });
    }

    /**
     * Dispatch the InsertEventsJob.
     * This method is called when the request is terminated.
     * Flushing the buffer manually is not needed since BatchedEventBuffer is bound as a scoped singleton and will be
     * flushed at the end of each request lifecycle.
     */
    private function dispatchEvents(): void
    {
        try {
            $buffer = App::make(BatchedEventBuffer::class);

            if ($buffer->isEmpty()) {
                return;
            }

            InsertEventsJob::dispatch($buffer->getAll());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
