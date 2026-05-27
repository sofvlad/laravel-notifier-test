<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\ChannelInterface;
use App\Enums\NotificationChannel;
use App\Models\Notification;

/**
 * Telegram notification delivery channel
 */
class TelegramChannel implements ChannelInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return NotificationChannel::TELEGRAM->value;
    }

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification): void
    {
        // @TODO: implement Telegram notification delivery
    }
}
