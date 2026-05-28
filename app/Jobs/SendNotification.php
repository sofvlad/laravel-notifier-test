<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job for creating and sending notifications asynchronously
 */
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
     * Execute the job
     */
    public function handle(NotificationService $notificationService): void
    {
        $logger = Log::channel('notifier');

        $notification = Notification::find($this->notificationId);
        if (!$notification) {
            $logger->error("Notification not found", ['id' => $this->notificationId]);

            return;
        }

        try {
            $notificationService->send($notification);

            $notification->update([
                'status' => NotificationStatus::SENT,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $notification->update([
                'status' => NotificationStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            $logger->error(
                'Failed to send notification',
                [
                    'id' => $notification->id,
                    'user_id' => $notification->userId,
                    'channel' => $notification->channel,
                    'error' => $e->getMessage(),
                ]
            );

            return;
        }

        $logger->info(
            'Notification sent successfully',
            [
                'id' => $notification->id,
                'user_id' => $notification->userId,
                'channel' => $notification->channel,
            ]
        );
    }
}
