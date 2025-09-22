<?php

namespace NiekPH\LaravelVisitorTracking\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use NiekPH\LaravelVisitorTracking\Middleware\TrackPageView;
use NiekPH\LaravelVisitorTracking\VisitorTrackingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

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

    public function getEnvironmentSetUp($app): void
    {
        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        tap($app['config'], function (Repository $config) {
            $config->set('session.encrypt', false);
            $config->set('database.default', 'testbench');
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
