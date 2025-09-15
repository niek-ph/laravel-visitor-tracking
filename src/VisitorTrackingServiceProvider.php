<?php

namespace NiekPH\LaravelVisitorTracking;

use NiekPH\LaravelVisitorTracking\Commands\VisitorTrackingCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VisitorTrackingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-visitor-tracking')
            ->hasConfigFile()
            ->hasMigration('create_visitors_table')
            ->hasMigration('create_events_table')
            ->hasCommand(VisitorTrackingCommand::class)
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishAssets()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('niek-ph/laravel-visitor-tracking');
            });
    }
}
