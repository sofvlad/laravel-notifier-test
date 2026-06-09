<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Services\Notifications\NotificationService;

readonly class StoreNotificationAction
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function execute(
        array $userIds,
        string $message,
        NotificationChannel $channel,
        NotificationPriority $priority = NotificationPriority::DEFAULT,
    ): array {
        $notifications = [];
        foreach ($userIds as $userId) {
            if (empty($userId)) {
                continue;
            }

            $notifications[] = $this->notificationService->create($userId, $message, $channel, $priority);
        }

        return $notifications;
    }
}
