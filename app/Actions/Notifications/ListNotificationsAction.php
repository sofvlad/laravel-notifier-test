<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\DTO\Notifications\ListNotificationsParams;
use App\Models\Notification;
use Illuminate\Support\Arr;

class ListNotificationsAction
{
    /**
     * List notifications with optional filtering and pagination
     */
    public function execute(ListNotificationsParams $params): array
    {
        $query = Notification::query()
            ->where('user_id', $params->userId);

        if ($params->status !== null) {
            $query->where('status', $params->status);
        }

        if ($params->channel !== null) {
            $query->where('channel', $params->channel);
        }

        return Arr::only($query->latest()->paginate($params->perPage)->toArray(), [
            'data',
            'current_page',
            'last_page',
            'per_page',
            'from',
            'to',
            'total',
        ]);
    }
}
