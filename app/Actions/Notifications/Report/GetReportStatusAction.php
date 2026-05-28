<?php

declare(strict_types=1);

namespace App\Actions\Notifications\Report;

use App\Models\NotificationsReport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class GetReportStatusAction
{
    /**
     * Get report
     *
     * @throws ModelNotFoundException
     */
    public function execute(string $uuid, ?int $createdBy = null): ?NotificationsReport
    {
        return NotificationsReport::where('created_by', $createdBy ?? Auth::id())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
