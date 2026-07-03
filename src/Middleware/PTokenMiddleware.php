<?php

declare(strict_types=1);

namespace Wenbo\PToken\Laravel\Middleware;

use Wenbo\PToken\PToken;
use Wenbo\PToken\Laravel\PTokenUser;
use Wenbo\PToken\Laravel\Exceptions\PTokenAuthException;
use Closure;
use Illuminate\Http\Request;

class PTokenMiddleware
{
    private const string TOKEN_PREFIX = 'Bearer ';

    public function __construct(
        private readonly PToken $ptoken,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $token = $this->extractToken($request);
        if ($token === null) {
            throw new PTokenAuthException('Token is missing');
        }

        $tokenData = $this->ptoken->get($token);

        if ($tokenData === null) {
            throw new PTokenAuthException('Token is invalid or expired');
        }

        $new_token = $tokenData['new_token'] ?? null;
        unset($tokenData['new_token']);

        $request = $this->injectTokenUser($request, $tokenData);

        $response = $next($request);

        // Inject new token header if token was rotated
        if ($new_token !== null) {
            $headerName = $this->ptoken->getConfig()->new_token_header;
            $response->headers->set($headerName, $new_token);
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        return $this->isPathSkipped($request);
    }

    private function isPathSkipped(Request $request): bool
    {
        $path = '/' . ltrim($request->path(), '/');

        foreach ($this->ptoken->getConfig()->auth_exclude_paths as $excludePath) {
            if (str_starts_with($path, $excludePath)) {
                return true;
            }
        }

        return false;
    }

    private function extractToken(Request $request): ?string
    {
        $headerName = $this->ptoken->getConfig()->token_header;
        $header = $request->header($headerName);

        if ($header !== null && $header !== '') {
            return str_starts_with($header, self::TOKEN_PREFIX)
                ? substr($header, strlen(self::TOKEN_PREFIX))
                : $header;
        }

        return $request->query('token');
    }

    private function injectTokenUser(Request $request, array $tokenData): Request
    {
        $config = $this->ptoken->getConfig();

        $tokenUser = new PTokenUser(
            $tokenData['token_id'] ?? '',
            $tokenData['user_key'],
            $tokenData['data'],
            $tokenData['abilities'] ?? ['*'],
            $tokenData['create_at'],
            $tokenData['expire_at'],
            $config->user_model,
            $tokenData['device'] ?? null,
        );

        $request->attributes->set('ptokenUser', $tokenUser);
        $request->attributes->set('ptokenUserKey', $tokenData['user_key']);
        $request->attributes->set('ptokenData', $tokenData['data']);
        $request->attributes->set('ptokenAbilities', $tokenData['abilities'] ?? ['*']);

        return $request;
    }
}
