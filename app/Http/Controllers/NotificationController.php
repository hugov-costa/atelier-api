<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Support\Pagination;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

#[Group('Notifications')]
class NotificationController extends Controller
{
    /**
     * List notifications
     *
     * Returns a paginated collection of the authenticated user's in-app notifications.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    #[QueryParameter('unread', 'Return only unread notifications.', required: false, type: 'boolean')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        $query = $request->boolean('unread')
            ? $user->unreadNotifications()
            : $user->notifications();

        $notifications = $query->paginate(Pagination::perPage($request->integer('per_page', 15)));

        return NotificationResource::collection($notifications)->additional(['message' => null]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        $user->unreadNotifications->markAsRead();

        return response()->noContent();
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return ApiResponse::item(new NotificationResource($notification));
    }
}
