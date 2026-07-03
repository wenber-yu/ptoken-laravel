<?php

declare(strict_types=1);

namespace Wenbo\PToken\Laravel\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 认证失败时抛出的专属异常。
 * Laravel 异常处理器将自动转换为 401 JSON 响应。
 */
class PTokenAuthException extends HttpException
{
    public function __construct(string $message = 'Authentication failed', int $code = 0)
    {
        parent::__construct(401, $message, null, [], $code);
    }
}
