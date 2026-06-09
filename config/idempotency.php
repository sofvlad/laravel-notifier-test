<?php

return [
    'enabled'      => env('IDEMPOTENCY_ENABLED', true),
    'lock_ttl'     => env('IDEMPOTENCY_LOCK_TTL', 30),
    'response_ttl' => env('IDEMPOTENCY_RESPONSE_TTL', 3600),
    'prefix'       => env('IDEMPOTENCY_PREFIX', 'idempotency'),
];
