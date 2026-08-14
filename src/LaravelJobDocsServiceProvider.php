<?php

namespace Rjusm\LaravelJobDocs;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rjusm\LaravelJobDocs\Console\Commands\GenerateJobDocsCommand;
use Rjusm\LaravelJobDocs\Http\Controllers\JobDocsController;

class LaravelJobDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/job-docs.php', 'job-docs');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'job-docs');

        $this->publishes([
            __DIR__.'/../config/job-docs.php' => config_path('job-docs.php'),
        ], 'job-docs-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/job-docs'),
        ], 'job-docs-views');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateJobDocsCommand::class]);
        }

        if (! config('job-docs.enabled', true)) {
            return;
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('job-docs.route', 'docs'),
            'middleware' => config('job-docs.middleware', ['web']),
        ], function () {
            Route::get('/', [JobDocsController::class, 'index'])->name('job-docs.index');
            Route::get('/openapi.json', [JobDocsController::class, 'openapi'])->name('job-docs.openapi');
        });
    }
}
