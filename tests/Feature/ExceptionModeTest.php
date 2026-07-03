<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase as Orchestra;
use Wenbo\PToken\Laravel\ServiceProvider;
use Wenbo\PToken\Laravel\Middleware\PTokenMiddleware;
use Wenbo\PToken\Laravel\Exceptions\PTokenAuthException;

abstract class ExceptionModeTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $store = new \Illuminate\Cache\ArrayStore;
        $repo  = new \Illuminate\Cache\Repository($store);
        $app->singleton(\Illuminate\Contracts\Cache\Repository::class, fn() => $repo);
        $app->singleton('cache.store', fn() => $store);
        $app->instance('cache', $repo);

        $app['config']->set('ptoken', [
            'encrypt_key'        => '12345678901234567890123456789012',
            'timeout'            => 3600,
            'max_refresh'        => 600,
            'token_delimiter'     => '_',
            'multi_login'         => false,
            'user_model'          => null,
            'auth_exclude_paths'  => [],
            'cache_pre_key'       => 'ptoken:',
        ]);
    }
}

uses(ExceptionModeTestCase::class);

test('auth failure throws PTokenAuthException', function () {
    $this->app['router']->middleware(PTokenMiddleware::class)->get('/api/test', function () {
        return response()->json(['ok' => true]);
    });

    $this->withoutExceptionHandling();

    $this->expectException(PTokenAuthException::class);

    $this->get('/api/test');
});
