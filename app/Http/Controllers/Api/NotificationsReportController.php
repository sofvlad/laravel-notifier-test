<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Notifications\Report\DownloadReportAction;
use App\Actions\Notifications\Report\GenerateReportAction;
use App\Actions\Notifications\Report\GetReportStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notification\Report\GenerateReportRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationsReportController extends Controller
{
    /**
     * Request generation of a new notifications report
     *
     * @param GenerateReportRequest $request
     * @param GenerateReportAction $generateAction
     *
     * @return JsonResponse
     */
    public function generate(
        GenerateReportRequest $request,
        GenerateReportAction $generateAction
    ): JsonResponse {
        $report = $generateAction->execute(
            $request->input('period_start'),
            $request->input('period_end'),
            $request->has('user_id') ? $request->integer('user_id') : null
        );

        return response()->json([
            'report_uuid' => $report->uuid,
            'status' => $report->status,
            'period' => [
                'start' => $report->period_start->toIso8601String(),
                'end' => $report->period_end->toIso8601String(),
            ],
            'message' => 'Report generation started',
        ], 202);
    }

    /**
     * Get status of a report generation
     *
     * @param string $uuid
     * @param GetReportStatusAction $getStatusAction
     *
     * @return JsonResponse
     */
    public function show(string $uuid, GetReportStatusAction $getStatusAction): JsonResponse
    {
        try {
            $report = $getStatusAction->execute($uuid);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'report_uuid' => $report->uuid,
            'status' => $report->status,
            'period' => [
                'start' => $report->period_start->toIso8601String(),
                'end' => $report->period_end->toIso8601String(),
            ],
        ]);
    }

    /**
     * Download a generated report
     *
     * @param string $uuid
     * @param GetReportStatusAction $getStatusAction
     * @param DownloadReportAction $downloadAction
     *
     * @return JsonResponse|StreamedResponse
     */
    public function download(
        string $uuid,
        GetReportStatusAction $getStatusAction,
        DownloadReportAction $downloadAction
    ): JsonResponse|StreamedResponse {
        try {
            $report = $getStatusAction->execute($uuid);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }

        if (!$report->isCompleted()) {
            return response()->json([
                'message' => 'Report is not ready for download',
                'status' => $report->status,
            ], 400);
        }
        if (!$downloadAction->fileExists($report)) {
            return response()->json([
                'message' => 'Report file not found',
            ], 404);
        }

        return $downloadAction->execute($report);
    }
}
