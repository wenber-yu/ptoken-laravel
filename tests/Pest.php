<?php

use Orchestra\Testbench\TestCase as Orchestra;

// 创建应用的工具函数
function createTestApp(array $config = [])
{
    return tap(new class($config) extends Orchestra {
        protected array $customConfig;

        public function __construct(array $config)
        {
            $this->customConfig = $config;
            parent::__construct('test');
        }

        protected function getPackageProviders($app): array
        {
            return [\Wenbo\PToken\Laravel\ServiceProvider::class];
        }

        protected function defineEnvironment($app): void
        {
            $store = new \Illuminate\Cache\ArrayStore;
            $repo  = new \Illuminate\Cache\Repository($store);
            $app->singleton(\Illuminate\Contracts\Cache\Repository::class, fn() => $repo);
            $app->singleton('cache.store', fn() => $store);
            $app->instance('cache', $repo);

            $app['config']->set('ptoken', array_merge([
                'encrypt_key'        => '12345678901234567890123456789012',
                'timeout'            => 3600,
                'token_delimiter'     => '_',
                'multi_login'         => false,
                'user_model'          => null,
                'auth_exclude_paths'  => ['/api/login', '/api/register'],
                'cache_pre_key'       => 'ptoken:',
            ], $this->customConfig));
        }
    }, fn($app) => $app->setUp());
}
