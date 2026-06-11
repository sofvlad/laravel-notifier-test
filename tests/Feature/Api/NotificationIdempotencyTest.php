<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Models\Notification;
use App\Models\User;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected IdempotencyService $idempotencyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user               = User::factory()->create();
        $this->token              = $this->user->createToken('test-token')->plainTextToken;
        $this->idempotencyService = app(IdempotencyService::class);
    }

    public function test_it_prevents_duplicate_notification_with_same_idempotency_key(): void
    {
        $idempotencyKey = 'unique-key-' . Str::uuid();

        $firstResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'Test notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $firstResponse->assertStatus(201);

        $secondResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'Test notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $secondResponse->assertStatus(201);

        $this->assertEquals($firstResponse->json('items.0.id'), $secondResponse->json('items.0.id'));
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_it_allows_different_notifications_with_different_idempotency_keys(): void
    {
        $key1 = 'key-1-' . Str::uuid();
        $key2 = 'key-2-' . Str::uuid();

        $response1 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $key1)
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'First notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $response1->assertStatus(201);

        $response2 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $key2)
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'Second notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $response2->assertStatus(201);

        $this->assertDatabaseCount('notifications', 2);

        $notifications = Notification::where('user_id', $this->user->id)->get();
        $this->assertNotEquals($notifications[0]->id, $notifications[1]->id);
    }

    public function test_it_returns_conflict_when_same_key_is_used_concurrently(): void
    {
        $idempotencyKey = 'test-concurrent-key-' . Str::uuid();
        $cacheKey       = "idempotency:POST:api/v1/notifications:{$idempotencyKey}";
        $requestId      = 'test-request-id-' . Str::uuid();

        // Simulate another request holding the lock
        $acquired = $this->idempotencyService->acquireLock($cacheKey, $requestId);
        $this->assertTrue($acquired);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('X-Request-Id', 'another-request-id')
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'Test notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $response->assertStatus(409)
            ->assertJsonFragment([
                'error' => 'Idempotency key is being processed',
            ])
            ->assertHeader('Retry-After');

        // Clean up the lock
        $this->idempotencyService->releaseLock($cacheKey, $requestId);
    }

    public function test_it_returns_cached_response_for_duplicate_request(): void
    {
        $idempotencyKey = 'cached-response-key-' . Str::uuid();
        $cacheKey       = "idempotency:POST:api/v1/notifications:{$idempotencyKey}";

        $firstResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'Test notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $firstResponse->assertStatus(201);
        $firstNotificationId = $firstResponse->json('items.0.id');

        $secondResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'Different message - should return cached',
                'channel'  => NotificationChannel::TELEGRAM->value,
                'priority' => NotificationPriority::CRITICAL->value,
            ]);

        $secondResponse->assertStatus(201)
            ->assertHeader('X-Idempotency-Cached', 'true')
            ->assertHeader('X-Idempotency-Key', $idempotencyKey);

        $secondNotificationId = $secondResponse->json('items.0.id');
        $this->assertEquals($firstNotificationId, $secondNotificationId);

        $this->assertDatabaseCount('notifications', 1);

        // Verify response is cached in Redis
        $responseKey = $cacheKey . ':response';
        $cachedData  = Redis::get($responseKey);
        $this->assertNotNull($cachedData);
        $this->assertStringContainsString((string) $firstNotificationId, (string) $cachedData);
    }

    public function test_it_requires_idempotency_key_for_notification_creation(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/notifications', [
                'user_id'  => $this->user->id,
                'message'  => 'Test notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $response->assertStatus(500);
    }

    public function test_idempotency_works_with_multiple_user_ids(): void
    {
        $user2          = User::factory()->create();
        $idempotencyKey = 'multi-user-key-' . Str::uuid();

        $firstResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/notifications', [
                'user_ids' => [$this->user->id, $user2->id],
                'message'  => 'Bulk notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $firstResponse->assertStatus(201);
        $firstCount = $firstResponse->json('items');

        $secondResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/notifications', [
                'user_ids' => [$this->user->id, $user2->id],
                'message'  => 'Bulk notification',
                'channel'  => NotificationChannel::EMAIL->value,
                'priority' => NotificationPriority::DEFAULT->value,
            ]);

        $secondResponse->assertStatus(201);
        $secondCount = $secondResponse->json('items');

        $this->assertEquals(count($firstCount), count($secondCount));
        $this->assertDatabaseCount('notifications', count($firstCount));
    }
}
