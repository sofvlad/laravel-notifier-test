<?php

declare(strict_types=1);

namespace App\Actions\Notifications\Report;

use App\Jobs\GenerateNotificationsReport;
use App\Models\NotificationsReport;
use Illuminate\Support\Facades\Auth;

class GenerateReportAction
{
    /**
     * Create and dispatch a new notifications report generation
     */
    public function execute(
        string $periodStart,
        string $periodEnd,
        ?int $userId = null,
    ): NotificationsReport {
        $report = NotificationsReport::create([
            'user_id'      => $userId,
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'status'       => 'pending',
            'created_by'   => Auth::id(),
        ]);

        GenerateNotificationsReport::dispatch($report->uuid);

        return $report;
    }
}
