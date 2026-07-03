<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    public function current(): Setting
    {
        return Setting::current();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): Setting
    {
        $setting = Setting::current();

        $setting->update($attributes);

        return $setting;
    }
}
