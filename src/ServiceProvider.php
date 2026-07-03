<?php

declare(strict_types=1);

namespace Wenbo\PToken\Laravel;

use Wenbo\PToken\Laravel\Commands\KeyCommand;
use Wenbo\PToken\PToken;
use Wenbo\PToken\PTokenConfig;
use Wenbo\PToken\Laravel\CacheDrivers\PTokenCacheDriver;
use Wenbo\PToken\Laravel\Middleware\PTokenMiddleware;
use Illuminate\Contracts\Cache\Repository;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ptoken.php', 'ptoken'
        );

        $this->app->singleton(PToken::class, function ($app) {
            $config = PTokenConfig::fromArray($app['config']->get('ptoken', []));
            $store  = $app->make(Repository::class);
            $driver = new PTokenCacheDriver($store);

            return new PToken($config, $driver);
        });

        // 注册路由中间件别名，用户通过 Route::middleware('ptoken.auth') 使用。
        // 也可在 app/Http/Kernel.php 中注册为全局中间件。
        $this->app['router']->aliasMiddleware('ptoken.auth', PTokenMiddleware::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/ptoken.php' => config_path('ptoken.php'),
        ], 'ptoken-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                KeyCommand::class,
            ]);
        }
    }
}
