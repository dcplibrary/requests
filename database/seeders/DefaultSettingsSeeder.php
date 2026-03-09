<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Dcplibrary\Sfp\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Seeds default settings only when the key does not exist.
 * Does not overwrite existing values. Safe to run anytime.
 *
 * Run by class name from the host app (after migrations are loaded):
 *
 *   php artisan db:seed --class=Dcplibrary\\Sfp\\Database\\Seeders\\DefaultSettingsSeeder
 *
 * Or from a package test / console:
 *
 *   (new \Dcplibrary\Sfp\Database\Seeders\DefaultSettingsSeeder())->run();
 */
class DefaultSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $illGroupId = SettingsSeeder::ensureIllGroupExists();
        $defaults = SettingsSeeder::defaultSettings($illGroupId);

        foreach ($defaults as $setting) {
            $key = $setting['key'];
            $exists = Setting::where('key', $key)->exists();

            if (! $exists) {
                $attrs = [
                    'key'         => $key,
                    'value'       => $setting['value'],
                    'label'       => $setting['label'],
                    'type'        => $setting['type'],
                    'group'       => $setting['group'],
                    'description' => $setting['description'] ?? '',
                ];
                if (isset($setting['tokens'])) {
                    $attrs['tokens'] = is_string($setting['tokens']) ? $setting['tokens'] : json_encode($setting['tokens']);
                }
                Setting::create($attrs);
                Cache::forget("setting:{$key}");
            }
        }
    }
}
