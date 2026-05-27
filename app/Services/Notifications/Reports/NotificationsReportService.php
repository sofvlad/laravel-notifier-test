<?php

declare(strict_types=1);

namespace App\Services\Notifications\Reports;

use App\Enums\NotificationChannel;
use App\Enums\ReportStatus;
use App\Models\NotificationsReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

class NotificationsReportService
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    public function __construct(
    ) {
        $this->logger = Log::channel('notifier');
    }

    /**
     * Generate report for the given report record
     *
     * @param NotificationsReport $report
     *
     * @return string Path to the generated file
     */
    public function generate(NotificationsReport $report): string
    {
        $report->update([
            'status' => ReportStatus::PROCESSING->value,
            'started_at' => now(),
        ]);

        $stats = $this->collectStatistics($report);

        $filePath = $this->createReportFile($report, $stats);

        $report->update([
            'status' => ReportStatus::COMPLETED->value,
            'file_path' => $filePath,
            'completed_at' => now(),
        ]);

        $this->logger->info('Report generated successfully', [
            'report_id' => $report->id,
            'user_id' => $report->user_id,
            'file_path' => $report->file_path,
        ]);

        return $filePath;
    }

    /**
     * Collect statistics from notifications
     *
     * @param NotificationsReport $report
     *
     * @return array{total: int, by_channel: array<string, array{total: int, errors: int}>}
     */
    protected function collectStatistics(NotificationsReport $report): array
    {
        $query = DB::table('notifications')
            ->select(
                'channel',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as errors")
            )
            ->whereBetween('created_at', [$report->period_start, $report->period_end])
            ->groupBy('channel');

        if ($report->user_id) {
            $query->where('user_id', $report->user_id);
        }

        $byChannel = [];
        $total = 0;

        foreach ($query->get() as $row) {
            $byChannel[$row->channel] = [
                'total' => (int)$row->total,
                'errors' => (int)$row->errors,
            ];
            $total += (int)$row->total;
        }

        // Ensure all known channels are present even with zero counts
        foreach (NotificationChannel::cases() as $channel) {
            if (!isset($byChannel[$channel->value])) {
                $byChannel[$channel->value] = ['total' => 0, 'errors' => 0];
            }
        }

        return [
            'total' => $total,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * Create the report file in storage
     *
     * @param NotificationsReport $report
     * @param array $stats
     *
     * @return string Path to the file
     */
    protected function createReportFile(NotificationsReport $report, array $stats): string
    {
        $jsonContent = json_encode([
            'report_id' => $report->id,
            'period' => [
                'start' => $report->period_start->toIso8601String(),
                'end' => $report->period_end->toIso8601String(),
            ],
            'user_id' => $report->user_id,
            'summary' => [
                'total_notifications' => $stats['total'],
            ],
            'by_channel' => array_map(function ($channelStats, $channel) {
                return [
                    'channel' => $channel,
                    'total' => $channelStats['total'],
                    'errors' => $channelStats['errors'],
                ];
            }, $stats['by_channel'], array_keys($stats['by_channel'])),
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $filename = sprintf(
            'reports/%s_%s_%s.json',
            $report->id,
            $report->period_start->format('YmdHis'),
            $report->period_end->format('YmdHis')
        );

        Storage::disk('local')->put($filename, $jsonContent);

        return $filename;
    }
}
