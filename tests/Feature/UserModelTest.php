<?php

declare(strict_types=1);

use Wenbo\PToken\Laravel\PTokenUser;
use Orchestra\Testbench\TestCase as Orchestra;
use Wenbo\PToken\Laravel\ServiceProvider;

abstract class UserModelTestCase extends Orchestra
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
            'token_delimiter'     => '.',
            'token_version'       => 'v1',
            'multi_login'         => false,
            'user_model'          => null,
            'auth_exclude_paths'  => [],
            'cache_pre_key'       => 'ptoken:',
        ]);
    }
}

uses(UserModelTestCase::class);

test('PTokenUser getUser returns null when no userModel configured', function () {
    $user = new PTokenUser('tid1', 'user1', ['role' => 'admin'], ['*'], time(), time() + 3600, null, null);

    $result = $user->getUser();
    expect($result)->toBeNull();
    expect($user->hasUser())->toBeTrue(); // 已解析，结果为 null
});

test('PTokenUser setUser and getUser works', function () {
    $user = new PTokenUser('tid2', 'user1', ['role' => 'admin'], ['*'], time(), time() + 3600, null, null);

    $mockModel = new stdClass();
    $mockModel->id = 'user1';
    $user->setUser($mockModel);

    expect($user->hasUser())->toBeTrue();
    expect($user->getUser())->toBe($mockModel);
});

test('PTokenUser hasUser returns false before resolved', function () {
    $user = new PTokenUser('tid3', 'user1', ['role' => 'admin'], ['*'], time(), time() + 3600, null, null);

    expect($user->hasUser())->toBeFalse();
});
