<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\NotificationsReport;
use App\Services\Notifications\Reports\NotificationsReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateNotificationsReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $reportUuid
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationsReportService $reportService): void
    {
        $logger = Log::channel('notifier');

        $report = NotificationsReport::where('uuid', $this->reportUuid)->first();

        if (! $report) {
            $logger->error('Report not found', ['report_uuid' => $this->reportUuid]);

            return;
        }

        try {
            $reportService->generate($report);
        } catch (Throwable $e) {
            $report->update([
                'status'        => ReportStatus::FAILED->value,
                'error_message' => $e->getMessage(),
            ]);

            $logger->error('Report generation failed', [
                'report_uuid' => $this->reportUuid,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return;
        }

        $logger->info('Report generated successfully', [
            'report_uuid' => $report->uuid,
            'user_id'     => $report->user_id,
            'file_path'   => $report->file_path,
        ]);
    }
}
