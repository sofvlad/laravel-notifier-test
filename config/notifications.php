<?php

use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\TelegramChannel;

/**
 * Notification channels configuration.
 *
 * Add new channels here to extend the notification system.
 * Each channel must implement ChannelInterface.
 */
return [
    'channels' => [
        'email' => EmailChannel::class,
        'telegram' => TelegramChannel::class,
    ],
];
