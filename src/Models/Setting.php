<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
     */
    public static function set(string $key, mixed $value): void
    {
        try {
            static::where('key', $key)->update(['value' => $value]);
            Cache::forget("setting:{$key}");
        } catch (\Throwable) {
            // In non-bootstrapped environments, silently no-op.
        }
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
