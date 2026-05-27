<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a report generation job
 */
enum ReportStatus: string
{
    /**
     * Report generation queued but not started
     */
    case PENDING = 'pending';

    /**
     * Report generation in progress
     */
    case PROCESSING = 'processing';

    /**
     * Report successfully generated and file ready
     */
    case COMPLETED = 'completed';

    /**
     * Report generation failed
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
