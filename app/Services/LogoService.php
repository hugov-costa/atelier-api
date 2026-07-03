<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;

class LogoService
{
    public const DISK = 'minio_public';

    private const MAX_DIMENSION = 1000;

    /**
     * Remove the stored logo file and clear its path.
     */
    public function delete(): Setting
    {
        $setting = Setting::current();

        if ($setting->logo_path !== null) {
            Storage::disk(self::DISK)->delete($setting->logo_path);

            $setting->forceFill(['logo_path' => null])->save();
        }

        return $setting;
    }

    /**
     * Store the uploaded logo on public object storage, replacing any previous
     * file, and record its path on the settings singleton.
     */
    public function store(UploadedFile $file): Setting
    {
        $setting = Setting::current();

        // Re-encode the upload to a fresh PNG: strips any embedded payload or crafted
        // extension, caps the pixel dimensions (decompression-bomb defence) and pins the
        // stored object to a safe type served from the public bucket.
        $contents = ImageManager::usingDriver(new Driver)
            ->decodeBinary($file->getContent())
            ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION)
            ->encode(new PngEncoder)
            ->toString();

        $path = 'atelier/logo/'.(string) Str::ulid().'.png';

        Storage::disk(self::DISK)->put($path, $contents);

        if ($setting->logo_path !== null) {
            Storage::disk(self::DISK)->delete($setting->logo_path);
        }

        $setting->forceFill(['logo_path' => $path])->save();

        return $setting;
    }

    /**
     * Public URL of the stored logo, or null when none is set.
     */
    public function url(): ?string
    {
        $path = Setting::current()->logo_path;

        if ($path === null) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
