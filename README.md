# PToken Laravel

PToken 的 Laravel 集成包，为 Laravel 应用提供服务端 Token 认证能力。支持中间件自动拦截、Token 能力（abilities）检查、User Model 自动关联等功能。

## 环境要求

- PHP >= 8.3
- Laravel >= 10.0
- `wenber-yu/ptoken-core`

## 安装

```bash
composer require wenber-yu/ptoken-laravel
```

Laravel 的自动包发现机制会自动注册 `ServiceProvider`，无需手动添加。

## 发布配置

```bash
php artisan vendor:publish --tag=ptoken-config
```

配置文件将发布到 `config/ptoken.php`。

## 生成密钥

```bash
# 仅生成并显示密钥
php artisan ptoken:key

# 生成密钥并自动写入 .env 文件
php artisan ptoken:key --env
```

`.env` 中配置：

```
PTOKEN_ENCRYPT_KEY=your-32-bytes-hex-key-here
```

## 配置说明

配置文件 `config/ptoken.php` 关键项：

```php
return [
    'cache_pre_key'   => 'ptoken:',         // 缓存键前缀（使用 Laravel Cache）
    'timeout'         => 604800,            // Token 有效期（秒），默认 7 天
    'max_refresh'     => 86400,             // 自动续期窗口（秒）
    'encrypt_key'     => env('PTOKEN_ENCRYPT_KEY', '...'),
    'multi_login'     => false,             // 多端登录
    'user_model'      => null,              // User Model 类名，如 'App\Models\User'
    'auth_exclude_paths' => [
        '/api/login',
        '/api/register',
    ],
];
```

## 中间件注册

`ServiceProvider` 自动注册了中间件别名 `ptoken.auth`，指向 `PTokenMiddleware`。

### 路由级注册

在路由文件中为指定路由组注册：

```php
use Illuminate\Support\Facades\Route;

Route::middleware('ptoken.auth')->group(function () {
    Route::get('/api/user', [UserController::class, 'info']);
    Route::post('/api/order', [OrderController::class, 'create']);
});
```

### 全局注册

在 `app/Http/Kernel.php` 中注册为全局中间件：

```php
protected $middlewareGroups = [
    'api' => [
        \Wenbo\PToken\Laravel\Middleware\PTokenMiddleware::class,
        // ...
    ],
];
```

## 完整示例

### 登录

```php
use Wenbo\PToken\PToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 验证用户名密码...
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => '认证失败'], 401);
        }

        $ptoken = app(PToken::class);
        $token = $ptoken->generate((string)$user->id, [
            'name'  => $user->name,
            'email' => $user->email,
        ]);

        return response()->json(['token' => $token]);
    }
}
```

### 获取当前用户

```php
public function me(Request $request)
{
    $tokenUser = $request->attributes->get('ptokenUser');

    // 方式一：直接从 data 中取
    $data = $request->attributes->get('ptokenData');

    // 方式二：自动关联 User Model（需配置 user_model）
    $user = $tokenUser->getUser();

    return response()->json([
        'userKey' => $request->attributes->get('ptokenUserKey'),
        'data'    => $data,
        'user'    => $user,
    ]);
}
```

### 登出

```php
public function logout(Request $request)
{
    $ptoken = app(PToken::class);

    // 从 Authorization header 提取 token
    $header = $request->header('Authorization');
    $token = str_replace('Bearer ', '', $header);

    $ptoken->destroy($token);

    return response()->json(['message' => '已登出']);
}
```

## Token 能力检查

认证通过后，可通过 `PTokenUser` 的 `tokenCan()` / `tokenCant()` 方法检查 Token 能力：

```php
public function updateProfile(Request $request)
{
    $tokenUser = $request->attributes->get('ptokenUser');

    // 检查 Token 是否有 write 能力
    if ($tokenUser->tokenCant('write')) {
        return response()->json(['message' => '无写入权限'], 403);
    }

    // 或直接获取能力列表
    $abilities = $request->attributes->get('ptokenAbilities');
    // ['read', 'write', ...]
}
```

> 能力不满足时也可抛出 `PTokenForbiddenException`（HTTP 403），配合全局异常处理器统一处理。

## 认证失败处理

认证失败时，中间件抛出 `PTokenAuthException`。可在 `app/Exceptions/Handler.php` 中统一捕获：

```php
use Wenbo\PToken\Laravel\Exceptions\PTokenAuthException;
use Wenbo\PToken\Exceptions\PTokenForbiddenException;

public function render($request, Throwable $e)
{
    if ($e instanceof PTokenAuthException) {
        return response()->json([
            'code'    => 401,
            'message' => $e->getMessage(),
        ], 401);
    }

    if ($e instanceof PTokenForbiddenException) {
        return response()->json([
            'code'    => 403,
            'message' => $e->getMessage(),
        ], 403);
    }

    return parent::render($request, $e);
}
```

## User Model 关联

配置 `user_model` 后，中间件认证通过时自动通过 Eloquent 查询关联 User Model：

```php
// config/ptoken.php
'user_model' => \App\Models\User::class,

// 控制器中
$tokenUser = $request->attributes->get('ptokenUser');
$user = $tokenUser->getUser();  // App\Models\User 实例（懒加载，首次调用时查询数据库）
```

`getUser()` 首次调用时执行 `User::find($userKey)`，后续调用直接返回缓存实例。也可手动设置：

```php
$tokenUser->setUser($customUser);
```

## 命令行工具

| 命令 | 说明 |
| --- | --- |
| `php artisan ptoken:key` | 生成 32 字节加密密钥 |
| `php artisan ptoken:key --env` | 生成密钥并自动写入 `.env` |

## 安全建议

1. **生产环境必须更换 `encrypt_key`**，使用 `php artisan ptoken:key --env` 生成
2. 将 `PTOKEN_ENCRYPT_KEY` 写入 `.env`，不要硬编码在配置文件中
3. 启用 HTTPS 防止 Token 被窃取
4. 根据安全策略合理设置 `timeout` 和 `max_refresh`
5. `auth_exclude_paths` 中确保登录、注册等公开接口正确配置

## 相关链接

- [PToken Core](https://github.com/wenber-yu/ptoken-core) — 核心库文档
- [PToken Hyperf](https://github.com/wenber-yu/ptoken-hyperf) — Hyperf 集成包

## 许可证

[MIT](LICENSE)
