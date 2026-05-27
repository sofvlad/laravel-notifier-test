<?php

namespace App\Contracts\Notifications;

use App\Models\Notification;

/**
 * Contract for notification delivery channels
 */
interface ChannelInterface
{
    /**
     * Get the unique identifier for this channel
     *
     * @return string The channel name
     */
    public function getName(): string;

    /**
     * Send the notification through this channel
     *
     * @param  Notification  $notification  The notification to send
     */
    public function send(Notification $notification): void;
}
