<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationChannel: string
{
    case EMAIL    = 'email';
    case TELEGRAM = 'telegram';

    /**
     * Get all available channel values as strings
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
