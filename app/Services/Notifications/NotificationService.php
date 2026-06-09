<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for creating and managing notifications
 */
readonly class NotificationService
{
    protected LoggerInterface $logger;

    public function __construct(
        private ChannelManager $channelManager
    ) {
        $this->logger = Log::channel('notifier');
    }

    /**
     * Create and send a notification
     */
    public function create(
        int $userId,
        string $message,
        NotificationChannel $channel,
        NotificationPriority $priority = NotificationPriority::DEFAULT
    ): Notification {
        $this->logger->info(
            'Creating notification',
            [
                'user_id'  => $userId,
                'message'  => $message,
                'channel'  => $channel->value,
                'priority' => $priority->value,
            ]
        );

        $notification = Notification::create([
            'user_id'  => $userId,
            'message'  => $message,
            'channel'  => $channel->value,
            'priority' => $priority->value,
            'status'   => NotificationStatus::PENDING,
        ]);

        SendNotification::dispatch($notification->id)->onQueue(match ($priority) {
            NotificationPriority::CRITICAL => 'notifications_critical',
            NotificationPriority::DEFAULT  => 'notifications',
        });

        $this->logger->info(
            'Notification created successfully',
            [
                'id'       => $notification->id,
                'user_id'  => $userId,
                'priority' => $priority->value,
                'channel'  => $channel->value,
            ]
        );

        return $notification->fresh();
    }

    /**
     * Send a notification
     *
     * @throws Throwable
     */
    public function send(Notification $notification): void
    {
        $this->channelManager->send($notification);
    }
}
