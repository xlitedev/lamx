<?php

namespace Xlited\Lamx;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Engines\EngineResolver;
use Xlited\Lamx\Http\Controllers\ActionController;
use Xlited\Lamx\Providers\BladeDirectives;
use Xlited\Lamx\View\LamxCompilerEngine;

class LamxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'lamx');

        $this->app->singleton('lamx', fn ($app) => new Lamx($app));
        $this->app->alias('lamx', Lamx::class);
    }

    public function boot(): void
    {
        BladeDirectives::register();

        $this->registerBladeEngine();
        $this->registerRequestMacros();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('lamx.php'),
            ], 'lamx-config');
        }
    }

    /**
     * Swap the Blade engine for one that binds $this to the rendering component.
     */
    protected function registerBladeEngine(): void
    {
        /** @var EngineResolver $resolver */
        $resolver = $this->app->make('view.engine.resolver');

        $resolver->register('blade', function () {
            $engine = new LamxCompilerEngine($this->app['blade.compiler'], $this->app['files']);

            $this->app->terminating(static function () use ($engine) {
                $engine->forgetCompiledOrNotExpired();
            });

            return $engine;
        });
    }

    protected function registerRequestMacros(): void
    {
        Request::macro('isHtmx', function () {
            /** @var Request $this */
            return $this->headers->has('HX-Request');
        });

        Request::macro('isHtmxBoosted', function () {
            /** @var Request $this */
            return $this->headers->has('HX-Boosted');
        });
    }

    protected function registerRoutes(): void
    {
        if (method_exists($this->app, 'routesAreCached') && $this->app->routesAreCached()) {
            return;
        }

        $config = $this->app['config']->get('lamx.route', []);

        Route::group([
            'prefix' => $config['prefix'] ?? 'lamx',
            'middleware' => $config['middleware'] ?? ['web'],
        ], function () use ($config) {
            Route::any('{component}/{action}', ActionController::class)
                ->where(['component' => '[A-Za-z0-9_.:-]+', 'action' => '[A-Za-z_][A-Za-z0-9_]*'])
                ->name($config['name'] ?? 'lamx.action');
        });
    }
}
