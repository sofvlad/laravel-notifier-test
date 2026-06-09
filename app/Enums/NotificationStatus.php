<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a notification in the delivery lifecycle
 */
enum NotificationStatus: string
{
    /**
     * Notification created, waiting to be processed
     */
    case PENDING = 'pending';

    /**
     * Notification is currently being processed/sent
     */
    case PROCESSING = 'processing';

    /**
     * Notification sent successfully
     */
    case SENT = 'sent';

    /**
     * Notification failed to send
     */
    case FAILED = 'failed';

    /**
     * Get all status values as strings
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
