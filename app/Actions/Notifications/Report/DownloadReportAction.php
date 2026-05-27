<?php

declare(strict_types=1);

namespace App\Actions\Notifications\Report;

use App\Models\NotificationsReport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadReportAction
{
    /**
     * Download a report file
     */
    public function execute(NotificationsReport $report): StreamedResponse
    {
        return Storage::disk('local')->download($report->file_path);
    }

    /**
     * Check if report file exists
     */
    public function fileExists(NotificationsReport $report): bool
    {
        return Storage::disk('local')->exists($report->file_path);
    }
}
