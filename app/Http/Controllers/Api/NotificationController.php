<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Notifications\GetNotificationAction;
use App\Actions\Notifications\ListNotificationsAction;
use App\Actions\Notifications\StoreNotificationAction;
use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notification\ListNotificationsRequest;
use App\Http\Requests\Api\Notification\StoreNotificationRequest;
use App\Models\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing notifications
 */
class NotificationController extends Controller
{
    /**
     * List notifications for a user with optional filtering
     *
     * @param ListNotificationsRequest $request
     * @param ListNotificationsAction $listAction
     *
     * @return JsonResponse
     */
    public function index(
        ListNotificationsRequest $request,
        ListNotificationsAction $listAction
    ): JsonResponse {
        $notifications = $listAction->execute(
            $request->integer('user_id'),
            $request->has('status') ? $request->string('status')->toString() : null,
            $request->has('channel') ? $request->string('channel')->toString() : null
        );

        return response()->json($notifications);
    }

    /**
     * Get a specific notification by UUID
     *
     * @param string $uuid
     * @param GetNotificationAction $getNotificationAction
     *
     * @return JsonResponse
     */
    public function show(string $uuid, GetNotificationAction $getNotificationAction): JsonResponse
    {
        try {
            return response()->json($getNotificationAction->execute($uuid));
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Report not found',
            ], 404);
        }
    }

    /**
     * Create a new notification
     *
     * @param StoreNotificationRequest $request
     * @param StoreNotificationAction $storeAction
     *
     * @return JsonResponse
     */
    public function store(
        StoreNotificationRequest $request,
        StoreNotificationAction $storeAction
    ): JsonResponse {
        $notification = $storeAction->execute(
            $request->integer('user_id'),
            $request->string('message')->toString(),
            NotificationChannel::from($request->string('channel')->toString())
        );

        return response()->json($notification, 201);
    }
}
