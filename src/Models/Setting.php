<?php

namespace Dcplibrary\Requests\Models;

use Dcplibrary\Requests\Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Key/value package settings with typed values, grouping, and cache-backed reads.
 *
 * @property int         $id
 * @property string      $key
 * @property string|null $value
 * @property string|null $label
 * @property string|null $type
 * @property string|null $group
 * @property string|null $description
 * @property array|null  $tokens
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'type', 'group', 'description', 'tokens'];

    protected $casts = ['tokens' => 'array'];

    /**
     * Get a setting value by key, with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // In isolated unit tests (no Laravel app boot), facades won't have a root.
        // Fail closed to the provided default rather than throwing.
        try {
            // Cache only raw attributes, not the Eloquent model object, to avoid
            // array-to-string errors when the cache driver serializes/deserializes it.
            $attrs = Cache::remember("setting:{$key}", 3600, function () use ($key) {
                $row = static::where('key', $key)->first();
                return $row ? ['type' => $row->type, 'value' => $row->value] : null;
            });
        } catch (\Throwable) {
            return $default;
        }

        if (! $attrs) {
            return $default;
        }

        return match ($attrs['type']) {
            'boolean' => (bool) $attrs['value'],
            'integer' => (int) $attrs['value'],
            default   => $attrs['value'],
        };
    }

    /**
     * Set a setting value by key and bust the cache.
     *
     * Inserts a minimal row when the key is missing so programmatic toggles
     * (tests, installers) are not no-ops — previously only UPDATE ran, so
     * missing rows left {@see static::get()} defaults in effect.
     */
    public static function set(string $key, mixed $value): void
    {
        try {
            $toStore = match (true) {
                is_bool($value) => $value ? '1' : '0',
                $value === null => null,
                default => (string) $value,
            };

            if (static::query()->where('key', $key)->exists()) {
                static::query()->where('key', $key)->update(['value' => $toStore]);
            } else {
                $row = static::baseAttributesForNewKey($key);
                $row['value'] = $toStore;
                static::query()->create($row);
            }

            Cache::forget("setting:{$key}");
        } catch (\Throwable) {
            // In non-bootstrapped environments, silently no-op.
        }
    }

    /**
     * Metadata for a new settings row — must match {@see SettingsSeeder} so admin UIs
     * that filter by group (e.g. Notifications) still see the key.
     */
    private static function baseAttributesForNewKey(string $key): array
    {
        foreach (SettingsSeeder::defaultSettings(0) as $def) {
            if (($def['key'] ?? '') !== $key) {
                continue;
            }
            $tokens = $def['tokens'] ?? null;
            if (is_string($tokens)) {
                $tokens = json_decode($tokens, true);
            }

            return [
                'key' => $key,
                'label' => $def['label'] ?? Str::headline(str_replace('_', ' ', $key)),
                'type' => $def['type'] ?? 'string',
                'group' => $def['group'] ?? 'general',
                'description' => $def['description'] ?? null,
                'tokens' => $tokens,
            ];
        }

        return [
            'key' => $key,
            'label' => Str::headline(str_replace('_', ' ', $key)),
            'type' => 'string',
            'group' => 'general',
            'description' => null,
            'tokens' => null,
        ];
    }

    /**
     * Get all settings grouped by their group key.
     */
    public static function allGrouped(): \Illuminate\Support\Collection
    {
        // Ensure we return a base Support\Collection (not an Eloquent\Collection),
        // because downstream code may use collection key helpers like `only()`
        // (Eloquent\Collection::only is model-key based and will explode on grouped values).
        return static::orderBy('group')
            ->orderBy('label')
            ->get()
            ->groupBy('group')
            ->toBase();
    }
}
