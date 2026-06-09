<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationPriority: string
{
    case CRITICAL = 'critical';
    case DEFAULT  = 'default';

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
