<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetNotificationAction
{
    /**
     * Get report
     *
     * @throws ModelNotFoundException
     */
    public function execute(string $uuid): ?Notification
    {
        return Notification::where('uuid', $uuid)->firstOrFail();
    }
}
