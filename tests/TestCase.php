<?php

namespace NiekPH\LaravelVisitorTracking\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Application;
use NiekPH\LaravelVisitorTracking\Middleware\TrackPageView;
use NiekPH\LaravelVisitorTracking\VisitorTrackingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\load_migration_paths;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'NiekPH\\LaravelVisitorTracking\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            VisitorTrackingServiceProvider::class,
        ];
    }

    /**
     * Register the package migration, so RefreshDatabase runs it on the test connection.
     */
    protected function defineDatabaseMigrations(): void
    {
        load_migration_paths($this->app, __DIR__.'/database/migrations');
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // SQLite in memory by default. DB_CONNECTION=pgsql or DB_CONNECTION=mysql runs the tests on that
        // database, configured through DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD.
        tap($app['config'], function (Repository $config) {
            $config->set('session.encrypt', false);
            $config->set('database.default', env('DB_CONNECTION', 'testbench'));
            $config->set('database.connections.testbench', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);

        });
    }

    protected function defineRoutes($router): void
    {
        $router->group([
            'middleware' => [AddQueuedCookiesToResponse::class, TrackPageView::class],
        ], function ($router) {
            $router->get('/', function () {
                return response('<html><body>Test Page</body></html>')
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            })
                ->name('test.home');

            $router->get('/test-json', function () {
                return response()->json(['message' => 'success']);
            })
                ->name('test.json');

            $router->post('/test-post', function () {
                return response('<html><body>Posted</body></html>')
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            })
                ->name('test.post');
        });

    }
}
