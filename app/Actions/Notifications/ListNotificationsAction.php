<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;

class ListNotificationsAction
{
    /**
     * List notifications with optional filtering
     */
    public function execute(
        int $userId,
        ?string $status = null,
        ?string $channel = null
    ): Collection {
        $query = Notification::query()
            ->where('user_id', $userId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($channel !== null) {
            $query->where('channel', $channel);
        }

        return $query->latest()->get();
    }
}
