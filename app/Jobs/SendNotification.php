<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\Notifications\TemporaryNotificationException;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $notificationId
    ) {}

    /**
     * Get logger instance
     */
    protected function logger(): LoggerInterface
    {
        return Log::channel('notifier');
    }

    /**
     * Execute the job
     *
     * At-least-once delivery guarantee with idempotency:
     * - If job fails after send(), retry will occur but send() is protected
     * - Status check prevents duplicate sends
     * - Atomic status transition prevents race conditions
     *
     * @throws Throwable
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $notification = Notification::findOrFail($this->notificationId);
        } catch (ModelNotFoundException $e) {
            $this->handleException($e);

            return;
        }

        try {
            $updated = Notification::where('id', $notification->id)
                ->where(function ($query) {
                    $query->where('status', NotificationStatus::PENDING)
                        ->orWhere('status', NotificationStatus::FAILED);
                })
                ->update(['status' => NotificationStatus::PROCESSING]);

            $this->logger()->debug(
                'The notification marking as processing',
                [
                    'notification_id' => $this->notificationId,
                    'event'           => 'notification_change_status',
                ]
            );

            if (! $updated) {
                throw new RuntimeException('Notification already being processed or already sent');
            }

            $notification = $notification->fresh();
            $notificationService->send($notification);
            $notification->update([
                'status'  => NotificationStatus::SENT,
                'sent_at' => now(),
            ]);

            $this->logger()->debug(
                'The notification marking as sent',
                [
                    'notification_id' => $this->notificationId,
                    'event'           => 'notification_change_status',
                ]
            );

            $this->logger()->info(
                'Notification sent successfully',
                [
                    'notification_id' => $notification->id,
                    'attempt'         => $this->attempts(),
                    'user_id'         => $notification->user_id,
                    'channel'         => $notification->channel,
                    'event'           => 'notification_sent',
                ]
            );
        } catch (Throwable $e) {
            $this->handleException($e, $notification);
        }
    }

    /**
     * Handle job failure with proper error classification
     *
     * @throws Throwable
     */
    protected function handleException(Throwable $e, ?Notification $notification = null): void
    {
        if ($notification !== null) {
            Notification::where('id', $notification->id)
                ->update([
                    'status'        => NotificationStatus::FAILED,
                    'error_message' => $e->getMessage(),
                ]);

            $this->logger()->debug(
                'The notification marking as failed',
                [
                    'notification_id' => $this->notificationId,
                    'event'           => 'notification_change_status',
                ]
            );
        }

        $this->logger()->error(
            'The sending of the notification was unsuccessful',
            array_filter([
                'notification_id' => $this->notificationId,
                'attempt'         => $this->attempts(),
                'user_id'         => $notification?->user_id,
                'channel'         => $notification?->channel,
                'error'           => $e->getMessage(),
                'event'           => 'notification_sending_failed',
            ])
        );

        if ($e instanceof TemporaryNotificationException) {
            throw $e;
        }
    }
}
