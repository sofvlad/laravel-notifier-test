<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Exceptions\Notifications\TemporaryNotificationException;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const int TRIES = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Notification $notification
    ) {}

    /**
     * Get logger instance
     */
    protected function logger(): LoggerInterface
    {
        return Log::channel('notifier');
    }

    /**
     * Get the number of times to attempt this job before failing.
     */
    public function tries(): int
    {
        return self::TRIES;
    }

    /**
     * Get the backoff delays based on notification priority.
     *
     * Returns array of delays in seconds for each retry attempt.
     */
    public function backoff(): array
    {
        return match ($this->notification->priority) {
            NotificationPriority::CRITICAL => [1, 3, 5, 10],
            default                        => [10, 30, 60, 300],
        };
    }

    /**
     * Determine if the job should be released back to the queue.
     */
    public function releaseAfterSeconds(): int
    {
        $backoff = $this->backoff();
        $attempt = $this->attempts();

        return $backoff[$attempt - 1] ?? $backoff[array_key_last($backoff)];
    }

    /**
     * Execute the job
     *
     * At-least-once delivery guarantee with idempotency:
     * - If job fails after send(), retry will occur but send() is protected
     * - Status check prevents duplicate sends
     * - Atomic status transition prevents race conditions
     */
    public function handle(NotificationService $notificationService): void
    {
        $notification = $this->notification;
        $attempt = $this->attempts();
        $maxAttempts = $this->tries();
        $lastAttempt = now();

        try {
            $updated = false;
            if ($notification->status === NotificationStatus::PENDING) {
                $updated = Notification::where('id', $notification->id)
                    ->where('status', NotificationStatus::PENDING)
                    ->update([
                        'status'          => NotificationStatus::PROCESSING,
                        'attempt'         => $attempt,
                        'last_attempt_at' => $lastAttempt,
                    ]);
            }

            if (! $updated && $notification->status !== NotificationStatus::PROCESSING) {
                throw new RuntimeException('Notification already being processed or already sent');
            }

            $this->logger()->debug(
                'The notification marking as processing',
                [
                    'notification_id' => $notification->id,
                    'priority'        => $notification->priority?->value,
                    'attempt'         => $attempt,
                    'max_attempts'    => $maxAttempts,
                    'event'           => 'notification_change_status',
                ]
            );

            $notification = $notification->fresh();
            $notificationService->send($notification);

            $notification->update([
                'status'          => NotificationStatus::SENT,
                'attempt'         => $attempt,
                'last_attempt_at' => $lastAttempt,
                'error_message'   => null,
                'sent_at'         => now(),
            ]);

            $this->logger()->debug(
                'The notification marking as sent',
                [
                    'notification_id' => $notification->id,
                    'event'           => 'notification_change_status',
                ]
            );

            $this->logger()->info(
                'Notification sent successfully',
                [
                    'notification_id' => $notification->id,
                    'user_id'         => $notification->user_id,
                    'channel'         => $notification->channel,
                    'priority'        => $notification->priority?->value,
                    'attempt'         => $attempt,
                    'event'           => 'notification_sent',
                ]
            );
        } catch (Throwable $e) {
            $this->handleException($e, $notification, $attempt, $maxAttempts);
        }
    }

    /**
     * Handle job failure with proper error classification
     */
    protected function handleException(Throwable $e, Notification $notification, int $attempt, int $maxAttempts): void
    {
        $backoffDelay = $this->releaseAfterSeconds();

        $this->logger()->error(
            'The sending of the notification was unsuccessful',
            array_filter([
                'notification_id' => $notification->id,
                'user_id'         => $notification->user_id,
                'channel'         => $notification->channel,
                'priority'        => $notification->priority?->value,
                'attempt'         => $attempt,
                'max_attempts'    => $maxAttempts,
                'backoff_delay'   => $backoffDelay,
                'error'           => $e->getMessage(),
                'event'           => 'notification_sending_failed',
            ])
        );

        $lastAttempt = now();
        $isMaxAttempts = $attempt >= $maxAttempts;

        if ($isMaxAttempts || !$e instanceof TemporaryNotificationException) {
            $notification->update([
                'status'          => NotificationStatus::FAILED,
                'attempt'         => $attempt,
                'last_attempt_at' => $lastAttempt,
                'next_attempt_at' => null,
                'error_message'   => $e->getMessage(),
            ]);

            $loggerMessage = 'Notification failed permanently';
            if ($isMaxAttempts) {
                $loggerMessage .= ' after max attempts';
            }

            $this->logger()->warning($loggerMessage, [
                'notification_id' => $notification->id,
                'priority'        => $notification->priority?->value,
                'attempt'         => $attempt,
                'max_attempts'    => $maxAttempts,
                'event'           => 'notification_permanently_failed',
            ]);

            return;
        }

        $notification->update([
            'error_message'   => $e->getMessage(),
            'attempt'         => $attempt,
            'last_attempt_at' => $lastAttempt,
            'next_attempt_at' => $lastAttempt->clone()->addSeconds($backoffDelay),
        ]);

        $this->logger()->info(
            'Notification will be retried',
            [
                'notification_id' => $notification->id,
                'priority'        => $notification->priority?->value,
                'attempt'         => $attempt,
                'max_attempts'    => $maxAttempts,
                'backoff_delay'   => $backoffDelay . ' seconds',
                'remaining'       => $maxAttempts - $attempt,
                'event'           => 'notification_retry_pending',
            ]
        );

        $this->release($backoffDelay);
    }
}
