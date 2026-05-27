<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\ChannelInterface;
use App\Enums\NotificationChannel;
use App\Models\Notification;

/**
 * Email notification delivery channel
 */
class EmailChannel implements ChannelInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return NotificationChannel::EMAIL->value;
    }

    /**
     * {@inheritDoc}
     */
    public function send(Notification $notification): void
    {
        // @TODO: implement Email notification delivery
    }
}
