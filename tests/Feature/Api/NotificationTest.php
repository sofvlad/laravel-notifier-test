<?php

namespace Tests\Feature\Api;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_can_create_notification(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/notifications', [
                'user_id'       => $this->user->id,
                'message'       => 'Test notification message',
                'channel'       => NotificationChannel::EMAIL->value,
                'priority'      => NotificationPriority::DEFAULT->value,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'user_id'       => $this->user->id,
                'message'       => 'Test notification message',
                'channel'       => NotificationChannel::EMAIL->value,
                'status'        => NotificationStatus::SENT->value,
                'priority'      => NotificationPriority::DEFAULT->value,
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'       => $this->user->id,
            'message'       => 'Test notification message',
            'channel'       => NotificationChannel::EMAIL->value,
            'status'        => NotificationStatus::SENT->value,
            'priority'      => NotificationPriority::DEFAULT->value,
        ]);
    }

    public function test_create_notification_validates_message_length(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/notifications', [
                'user_id'       => $this->user->id,
                'message'       => str_repeat('a', 501),
                'channel'       => NotificationChannel::EMAIL->value,
                'priority'      => NotificationPriority::DEFAULT->value,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_create_notification_validates_channel(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/notifications', [
                'user_id' => $this->user->id,
                'message' => 'Test message',
                'channel' => 'unsupported',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_can_get_notification_status(): void
    {
        $notification = Notification::factory()->create([
            'user_id'       => $this->user->id,
            'message'       => 'Test message',
            'status'        => NotificationStatus::SENT,
            'channel'       => NotificationChannel::EMAIL->value,
            'priority'      => NotificationPriority::DEFAULT->value,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/notifications/{$notification->uuid}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id'     => $notification->id,
                'status' => $notification->status->value,
            ]);
    }

    public function test_can_list_user_notifications(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Notification::factory()->create([
                'user_id'       => $this->user->id,
                'message'       => "Message {$i}",
                'status'        => NotificationStatus::SENT,
                'channel'       => NotificationChannel::EMAIL->value,
                'priority'      => NotificationPriority::DEFAULT->value,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Notification::factory()->create([
                'user_id'       => $this->user->id,
                'message'       => "Telegram {$i}",
                'status'        => NotificationStatus::SENT,
                'channel'       => NotificationChannel::TELEGRAM->value,
                'priority'      => NotificationPriority::DEFAULT->value,
            ]);
        }

        Notification::factory()->create([
            'user_id'       => User::factory()->create()->id,
            'message'       => 'Other user',
            'status'        => NotificationStatus::SENT,
            'channel'       => NotificationChannel::EMAIL->value,
            'priority'      => NotificationPriority::DEFAULT->value,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/notifications?user_id={$this->user->id}");

        $response->assertStatus(200)
            ->assertJsonCount(5);
    }

    public function test_can_filter_notifications_by_status(): void
    {
        Notification::factory()->create([
            'user_id'       => $this->user->id,
            'message'       => 'Sent message',
            'status'        => NotificationStatus::SENT,
            'channel'       => NotificationChannel::EMAIL->value,
            'priority'      => NotificationPriority::DEFAULT->value,
        ]);

        Notification::factory()->create([
            'user_id'       => $this->user->id,
            'message'       => 'Failed message',
            'status'        => NotificationStatus::FAILED,
            'channel'       => NotificationChannel::EMAIL->value,
            'priority'      => NotificationPriority::DEFAULT->value,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson(sprintf(
                '/api/v1/notifications?user_id=%d&status=%s',
                $this->user->id,
                NotificationStatus::SENT->value
            ));

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['status' => NotificationStatus::SENT->value]);
    }

    public function test_can_filter_notifications_by_channel(): void
    {
        for ($i = 0; $i < 2; $i++) {
            Notification::factory()->create([
                'user_id'       => $this->user->id,
                'message'       => "Email {$i}",
                'status'        => NotificationStatus::SENT,
                'channel'       => NotificationChannel::EMAIL->value,
                'priority'      => NotificationPriority::DEFAULT->value,
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            Notification::factory()->create([
                'user_id'       => $this->user->id,
                'message'       => "Telegram {$i}",
                'status'        => NotificationStatus::SENT,
                'channel'       => NotificationChannel::TELEGRAM->value,
                'priority'      => NotificationPriority::DEFAULT->value,
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson(sprintf(
                '/api/v1/notifications?user_id=%d&channel=%s',
                $this->user->id,
                NotificationChannel::TELEGRAM->value
            ));

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonFragment(['channel' => NotificationChannel::TELEGRAM->value]);
    }
}
