<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase as Orchestra;
use Wenbo\PToken\Laravel\ServiceProvider;
use Wenbo\PToken\Laravel\Middleware\PTokenMiddleware;
use Wenbo\PToken\PToken;

abstract class PTokenMiddlewareTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $store = new \Illuminate\Cache\ArrayStore;
        $repo = new \Illuminate\Cache\Repository($store);
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
            'auth_exclude_paths'  => ['/api/login', '/api/register'],
            'cache_pre_key'       => 'ptoken:',
        ]);
    }
}

uses(PTokenMiddlewareTestCase::class);

function registerRoute($app)
{
    $app['router']->middleware(PTokenMiddleware::class)->get('/api/test', function () {
        return response()->json(['ok' => true]);
    });
}

function generateToken($app, string $userKey = 'user1'): string
{
    return $app->make(PToken::class)->generate($userKey, ['*'], ['name' => 'test'])['token'];
}

// 1. 无 token 返回 401
test('no token returns 401', function () {
    registerRoute($this->app);

    $this->get('/api/test')
        ->assertStatus(401);
});

// 2. 有效 token 通过
test('valid token passes', function () {
    registerRoute($this->app);

    $token = generateToken($this->app);

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->get('/api/test');

    $response->assertStatus(200)
        ->assertJson(['ok' => true]);
});

// 3. 无效 token 返回 401
test('invalid token returns 401', function () {
    registerRoute($this->app);

    $this->withHeader('Authorization', 'Bearer invalid_token_xxx')
        ->get('/api/test')
        ->assertStatus(401);
});

// 4. 过期 token 返回 401
test('expired token returns 401', function () {
    registerRoute($this->app);

    $token = generateToken($this->app);

    // 通过缓存修改 expireAt 为过去时间，模拟过期
    // token 格式: v1.{encryptedUserKey}.{tokenId}
    $delimiter = '.';
    $parts = explode($delimiter, $token, 4);
    $encryptedUserKey = $parts[1];
    $tokenId = $parts[2];
    $cacheKey = 'ptoken:' . $encryptedUserKey . '.' . $tokenId;

    $cache = $this->app->make(\Illuminate\Contracts\Cache\Repository::class);
    $cacheData = $cache->get($cacheKey);
    expect($cacheData)->not->toBeNull();

    $cacheData['expire_at'] = time() - 1;
    $cache->put($cacheKey, $cacheData, 3600);

    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->get('/api/test')
        ->assertStatus(401);
});

// 5. auth_exclude_paths 跳过
test('auth_exclude_paths skips auth', function () {
    $this->app['router']->middleware(PTokenMiddleware::class)->get('/api/login', function () {
        return response()->json(['ok' => true]);
    });

    $this->get('/api/login')
        ->assertStatus(200)
        ->assertJson(['ok' => true]);
});

// 6. multi_login=false 测试（每个 token 有独立 ID，旧 token 被正确销毁）
test('multi_login false invalidates old token', function () {
    $this->app['config']->set('ptoken.multi_login', false);
    $this->app->forgetInstance(PToken::class);

    registerRoute($this->app);

    $ptoken = $this->app->make(PToken::class);
    $result1 = $ptoken->generate('user1', ['*'], ['name' => 'first']);
    $token1 = $result1['token'];
    $result2 = $ptoken->generate('user1', ['*'], ['name' => 'second']);
    $token2 = $result2['token'];

    // token2 应该有效
    $this->withHeader('Authorization', 'Bearer ' . $token2)
        ->get('/api/test')
        ->assertStatus(200);

    // token1 已被销毁（multi_login=false 时新登录销毁旧 token）
    $this->withHeader('Authorization', 'Bearer ' . $token1)
        ->get('/api/test')
        ->assertStatus(401);
});
