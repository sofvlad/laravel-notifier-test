<?php

declare(strict_types=1);

namespace App\Exceptions\Notifications;

use Exception;

/**
 * Fake exception for testing retry logic
 */
class FakeTemporaryException extends Exception implements TemporaryNotificationException
{
    /**
     * Create a new fake temporary exception
     */
    public function __construct(string $message = 'Fake temporary error')
    {
        parent::__construct($message);
    }
}
