<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;

class StoreNotificationAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param int $userId
     * @param string $message
     * @param NotificationChannel $channel
     * @return Notification
     */
    public function execute(
        int $userId,
        string $message,
        NotificationChannel $channel
    ): Notification {
        return $this->notificationService->create($userId, $message, $channel);
    }
}
