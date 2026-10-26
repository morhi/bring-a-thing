<?php

namespace App\Http\Controllers;

use App\Data\NotificationListData;
use App\Data\SharedNotificationsData;
use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Http\Requests\Notifications\MarkAllNotificationsReadRequest;
use App\Http\Requests\Notifications\MarkNotificationReadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List one page of the signed-in account's notifications for the bell panel (F-23).
     */
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        return response()->json(NotificationListData::forUser($request->user(), $request->integer('page', 1)));
    }

    /**
     * Return the bell's unread count, e.g. after reconnecting the live channel.
     */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(SharedNotificationsData::forUser($request->user()));
    }

    /**
     * Mark one notification as read, typically when its entry is opened.
     *
     * Answers with the new unread count, since the panel calls it without a page visit.
     */
    public function markRead(MarkNotificationReadRequest $request): JsonResponse
    {
        $request->notification()->markAsRead();

        return response()->json(SharedNotificationsData::forUser($request->user()));
    }

    /**
     * Mark every notification of the account as read ("Mark all as read").
     */
    public function markAllRead(MarkAllNotificationsReadRequest $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(SharedNotificationsData::forUser($request->user()));
    }
}
