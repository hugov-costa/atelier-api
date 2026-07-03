<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcessAvatarUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarService
{
    public const DISK = 'minio_public';

    public const TEMP_DISK = 'minio_private';

    public function store(User $user, UploadedFile $file): User
    {
        $tempPath = 'avatars/tmp/'.(string) Str::ulid().'.tmp';

        Storage::disk(self::TEMP_DISK)->put($tempPath, $file->getContent());

        ProcessAvatarUpload::dispatch($user->id, $tempPath, $user->avatar_path);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        if ($user->avatar_path !== null) {
            Storage::disk(self::DISK)->delete($user->avatar_path);

            $user->forceFill(['avatar_path' => null])->save();
        }
    }

    public function url(User $user): ?string
    {
        if ($user->avatar_path === null) {
            return null;
        }

        return Storage::disk(self::DISK)->url($user->avatar_path);
    }
}
