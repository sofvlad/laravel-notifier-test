<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\Notifications\ChannelInterface;
use App\Models\Notification;
use InvalidArgumentException;
use Throwable;

/**
 * Manages notification channel registration and dispatching
 */
class ChannelManager
{
    /**
     * @var array<string, ChannelInterface>
     */
    protected array $channels = [];

    /**
     * Register all channels from configuration
     *
     * @param  iterable<ChannelInterface>  $channels
     */
    public function __construct(iterable $channels = [])
    {
        foreach ($channels as $channel) {
            $this->register($channel);
        }
    }

    /**
     * Register a channel with the manager.
     */
    public function register(ChannelInterface $channel): void
    {
        $this->channels[$channel->getName()] = $channel;
    }

    /**
     * Send a notification through the specified channel
     *
     * @throws InvalidArgumentException If the channel is not supported
     * @throws Throwable
     */
    public function send(Notification $notification): void
    {
        if (! isset($this->channels[$notification->channel])) {
            throw new InvalidArgumentException("Channel [{$notification->channel}] is not supported.");
        }

        $this->channels[$notification->channel]->send($notification);
    }

    /**
     * Get all registered channel names
     *
     * @return array<string>
     */
    public function getAvailableChannels(): array
    {
        return array_keys($this->channels);
    }
}
