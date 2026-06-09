<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Notifications\GetNotificationAction;
use App\Actions\Notifications\ListNotificationsAction;
use App\Actions\Notifications\StoreNotificationAction;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notification\ListNotificationsRequest;
use App\Http\Requests\Api\Notification\StoreNotificationRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Controller for managing notifications
 */
class NotificationController extends Controller
{
    /**
     * List notifications for a user with optional filtering
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
     */
    public function show(string $uuid, GetNotificationAction $getNotificationAction): JsonResponse
    {
        try {
            return response()->json($getNotificationAction->execute($uuid));
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }
    }

    /**
     * Create a new notification
     */
    public function store(
        StoreNotificationRequest $request,
        StoreNotificationAction $storeAction
    ): JsonResponse {
        $userIds = $request->array('user_ids');
        if (empty($userIds)) {
            if (! $request->has('user_id')) {
                throw new InvalidArgumentException('Either user_ids or user_id must be provided');
            }
            $userIds = [$request->integer('user_id')];
        }

        $notifications = $storeAction->execute(
            array_unique($userIds),
            $request->string('message')->toString(),
            NotificationChannel::from($request->string('channel')->toString()),
            NotificationPriority::from($request->string('priority')->toString())
        );

        return response()->json([
            'items' => $notifications,
        ], 201);
    }
}
