<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Redis\Factory as RedisFactory;

class IdempotencyService
{
    private const LOCK_SUFFIX = ':lock';
    private const RESPONSE_SUFFIX = ':response';

    public function __construct(
        private readonly RedisFactory $redis
    ) {}

    public function acquireLock(string $cacheKey, string $requestId): bool
    {
        $lockKey = $cacheKey . self::LOCK_SUFFIX;
        $ttl = config('idempotency.lock_ttl', 30);

        return $this->redis->set(
                $lockKey,
                $requestId,
                'EX',
                $ttl,
                'NX'
            ) !== null;
    }

    public function releaseLock(string $cacheKey, string $requestId): void
    {
        $lockKey = $cacheKey . self::LOCK_SUFFIX;

        $script = <<<'LUA'
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        LUA;

        $this->redis->eval($script, 1, $lockKey, $requestId);
    }

    public function getCachedResponse(string $cacheKey): ?array
    {
        $responseKey = $cacheKey . self::RESPONSE_SUFFIX;
        $data = $this->redis->get($responseKey);

        if ($data === null) {
            return null;
        }

        return json_decode($data, true, 512, JSON_THROW_ON_ERROR) ?: null;
    }

    public function cacheResponse(
        string $cacheKey,
        string $requestId,
        string $body,
        int $status,
        array $headers,
        ?int $ttl = null
    ): void {
        $responseKey = $cacheKey . self::RESPONSE_SUFFIX;
        $ttl = $ttl ?? config('idempotency.response_ttl', 3600);

        $flatHeaders = [];
        foreach ($headers as $name => $values) {
            $flatHeaders[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        $responseData = [
            'status' => $status,
            'body' => $body,
            'headers' => $flatHeaders,
            'request_id' => $requestId,
            'created_at' => now()->toISOString(),
        ];

        $this->redis->setex(
            $responseKey,
            $ttl,
            json_encode($responseData, JSON_THROW_ON_ERROR)
        );
    }
}
