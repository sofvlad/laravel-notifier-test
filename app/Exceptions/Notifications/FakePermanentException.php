<?php

declare(strict_types=1);

namespace App\Exceptions\Notifications;

use Exception;

/**
 * Fake exception for testing retry logic
 */
class FakePermanentException extends Exception
{
    /**
     * Create a new fake permanent exception
     */
    public function __construct(string $message = 'Fake permanent error')
    {
        parent::__construct($message);
    }
}
