<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Exceptions\Notifications\FakePermanentException;
use App\Exceptions\Notifications\FakeTemporaryException;
use App\Jobs\SendNotification;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createNotification(array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id'  => $this->user->id,
            'message'  => 'Test message',
            'channel'  => 'telegram',
            'status'   => NotificationStatus::PENDING->value,
            'priority' => NotificationPriority::DEFAULT->value,
            'attempt'  => 0,
        ], $overrides));
    }

    public function test_it_has_correct_tries(): void
    {
        $job = new SendNotification($this->createNotification());

        $this->assertEquals(5, $job->tries());
    }

    public function test_it_returns_correct_backoff(): void
    {
        $job = new SendNotification(
            $this->createNotification(['priority' => NotificationPriority::CRITICAL->value])
        );

        $this->assertEquals([1, 3, 5, 10], $job->backoff());
    }

    public function test_it_sets_sent_status_on_success(): void
    {
        $notification = $this->createNotification();

        $job         = new SendNotification($notification);
        $serviceMock = $this->createMock(NotificationService::class);
        $serviceMock->expects($this->once())
            ->method('send')
            ->with($this->callback(fn ($n) => $n->id === $notification->id))
            ->willReturnSelf();

        $job->handle($serviceMock);

        $notification->refresh();

        $this->assertEquals(NotificationStatus::SENT->value, $notification->status->value);
    }

    public function test_temporary_exception_triggers_retry_logic(): void
    {
        $notification = $this->createNotification([
            'status'  => NotificationStatus::PROCESSING->value,
            'attempt' => 1,
        ]);

        $serviceMock = $this->createMock(NotificationService::class);
        $serviceMock->expects($this->once())
            ->method('send')
            ->with($this->callback(fn ($n) => $n->id === $notification->id))
            ->willThrowException(new FakeTemporaryException('Temporary error'));

        $job = $this->getMockBuilder(SendNotification::class)
            ->setConstructorArgs([$notification])
            ->onlyMethods(['attempts'])
            ->getMock();
        $job->method('attempts')->willReturn(1);
        $job->handle($serviceMock);

        $notification->refresh();

        $this->assertEquals('Temporary error', $notification->error_message);
    }

    public function test_permanent_exception_marks_failed(): void
    {
        $notification = $this->createNotification([
            'status'  => NotificationStatus::PROCESSING->value,
            'attempt' => 3,
        ]);

        $serviceMock = $this->createMock(NotificationService::class);
        $serviceMock->expects($this->once())
            ->method('send')
            ->with($this->callback(fn ($n) => $n->id === $notification->id))
            ->willThrowException(new FakePermanentException('Permanent error'));

        $job = $this->getMockBuilder(SendNotification::class)
            ->setConstructorArgs([$notification])
            ->onlyMethods(['attempts'])
            ->getMock();
        $job->method('attempts')->willReturn(3);
        $job->handle($serviceMock);

        $notification->refresh();

        $this->assertEquals(NotificationStatus::FAILED->value, $notification->status->value);
        $this->assertEquals('Permanent error', $notification->error_message);
        $this->assertNull($notification->next_attempt_at);
    }

    public function test_max_attempts_marks_failed(): void
    {
        $notification = $this->createNotification([
            'status'  => NotificationStatus::PROCESSING->value,
            'attempt' => 5,
        ]);

        $serviceMock = $this->createMock(NotificationService::class);
        $serviceMock->expects($this->once())
            ->method('send')
            ->with($this->callback(fn ($n) => $n->id === $notification->id))
            ->willThrowException(new FakeTemporaryException('Temporary error'));

        $job = $this->getMockBuilder(SendNotification::class)
            ->setConstructorArgs([$notification])
            ->onlyMethods(['attempts'])
            ->getMock();
        $job->method('attempts')->willReturn(5);
        $job->handle($serviceMock);

        $notification->refresh();

        $this->assertEquals(NotificationStatus::FAILED->value, $notification->status->value);
        $this->assertEquals('Temporary error', $notification->error_message);
    }
}
