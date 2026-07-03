<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Http\Requests\Setting\UploadLogoRequest;
use App\Http\Resources\SettingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Services\LogoService;
use App\Services\SettingService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

#[Group('Settings', weight: 10)]
class SettingController extends Controller
{
    public function __construct(private SettingService $settings, private LogoService $logos) {}

    /**
     * Remove the atelier logo
     *
     * Deletes the stored logo. Restricted to staff.
     */
    public function destroyLogo(): Response
    {
        $this->authorize('update', Setting::class);

        $this->logos->delete();

        return response()->noContent();
    }

    /**
     * Show the atelier settings
     *
     * Returns the singleton pricing and billing configuration. Restricted to staff.
     */
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return ApiResponse::item(new SettingResource($this->settings->current()));
    }

    /**
     * Upload the atelier logo
     *
     * Accepts a `jpeg`, `png` or `webp` image up to 2 MB and stores it on public
     * object storage; it is embedded in billing statements. Restricted to staff.
     */
    public function storeLogo(UploadLogoRequest $request): JsonResponse
    {
        $file = $request->file('logo');

        if (! $file instanceof UploadedFile) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::item(new SettingResource($this->logos->store($file)));
    }

    /**
     * Update the atelier settings
     *
     * Updates the singleton configuration. Restricted to staff.
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        return ApiResponse::item(new SettingResource($this->settings->update($request->validated())));
    }
}
