<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationsReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/', [NotificationController::class, 'store']);
            Route::get('/{notification_uuid}', [NotificationController::class, 'show']);
        });

        Route::prefix('reports')->group(function () {
            Route::post('/notifications/generate', [NotificationsReportController::class, 'generate']);
            Route::get('/notifications/{report_uuid}', [NotificationsReportController::class, 'show']);
            Route::get('/notifications/{report_uuid}/download', [NotificationsReportController::class, 'download']);
        });
    });
});
