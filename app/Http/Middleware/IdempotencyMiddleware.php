<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

readonly class IdempotencyMiddleware
{
    public function __construct(
        private IdempotencyService $idempotencyService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            throw new RuntimeException('Idempotency key is required');
        }

        $requestId = $request->header('X-Request-Id', uniqid());
        $cacheKey  = "idempotency:{$request->method()}:{$request->path()}:{$key}";

        $cachedResponse = $this->idempotencyService->getCachedResponse($cacheKey);

        if ($cachedResponse !== null) {
            return response()->make(
                $cachedResponse['body'],
                $cachedResponse['status'],
                $cachedResponse['headers'] + ['X-Idempotency-Cached' => 'true']
            );
        }

        $acquired = $this->idempotencyService->acquireLock($cacheKey, $requestId);

        if (! $acquired) {
            $retryAfter = config('idempotency.lock_ttl', 30);

            return response()->json([
                'error'   => 'Idempotency key is being processed',
                'message' => "Request is already being processed. Please retry after {$retryAfter} seconds.",
            ], 409, ['Retry-After' => (string) $retryAfter]);
        }

        try {
            $response = $next($request);

            $this->idempotencyService->cacheResponse(
                $cacheKey,
                $requestId,
                $response->getContent(),
                $response->getStatusCode(),
                array_merge(
                    $response->headers->all(),
                    ['X-Idempotency-Key' => [$key]]
                )
            );

            $response->headers->set('X-Idempotency-Key', $key);

            return $response;
        } finally {
            $this->idempotencyService->releaseLock($cacheKey, $requestId);
        }
    }
}
