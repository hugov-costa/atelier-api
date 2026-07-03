<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Avatar\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AvatarService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

#[Group('Avatars', weight: 4)]
class AvatarController extends Controller
{
    public function __construct(private AvatarService $avatars) {}

    /**
     * Upload a profile image
     *
     * Accepts a `jpeg`, `png` or `webp` image up to 2 MB. The image is scaled to fit 512x512,
     * re-encoded to WebP and stored on public object storage. Accessible to the user or an admin.
     */
    public function store(UploadAvatarRequest $request, User $user): JsonResponse
    {
        $file = $request->file('avatar');

        if (! $file instanceof UploadedFile) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::item(new UserResource($this->avatars->store($user, $file)));
    }

    /**
     * Remove the profile image
     *
     * Deletes the stored avatar. Accessible to the user themselves or to administrators.
     */
    public function destroy(User $user): Response
    {
        $this->authorize('update', $user);

        $this->avatars->delete($user);

        return response()->noContent();
    }
}
