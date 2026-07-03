<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Transcodes an uploaded avatar off the request lifecycle so image processing
 * never blocks a web worker. The final object key carries a random token so the
 * public URL cannot be guessed from the (public) user ULID alone.
 */
class ProcessAvatarUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_DIMENSION = 512;

    public function __construct(
        private int $userId,
        private string $tempPath,
        private ?string $previousPath,
    ) {}

    public function handle(): void
    {
        $temp = Storage::disk(AvatarService::TEMP_DISK);

        if (! $temp->exists($this->tempPath)) {
            return;
        }

        $user = User::find($this->userId);
        $binary = $temp->get($this->tempPath);

        if (! $user instanceof User || $binary === null) {
            $temp->delete($this->tempPath);

            return;
        }

        $contents = ImageManager::usingDriver(new Driver)
            ->decodeBinary($binary)
            ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION)
            ->encode(new WebpEncoder(quality: 80))
            ->toString();

        $finalPath = 'avatars/'.$user->ulid.'/'.Str::random(40).'.webp';

        Storage::disk(AvatarService::DISK)->put($finalPath, $contents, 'public');

        $user->forceFill(['avatar_path' => $finalPath])->save();

        $temp->delete($this->tempPath);

        if (is_string($this->previousPath) && $this->previousPath !== '') {
            Storage::disk(AvatarService::DISK)->delete($this->previousPath);
        }
    }
}
