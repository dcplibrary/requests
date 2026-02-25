<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = [
        'title',
        'author',
        'publish_date',
        'isbn',
        'isbn13',
        'publisher',
        'exact_publish_date',
        'edition',
        'overview',
        'source',
        'material_type_id',
    ];

    protected $casts = [
        'exact_publish_date' => 'date',
    ];

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(SfpRequest::class);
    }

    /**
     * Find a matching material by normalized title and author.
     */
    public static function findMatch(string $title, string $author): ?self
    {
        return static::whereRaw('LOWER(title) = ?', [strtolower(trim($title))])
            ->whereRaw('LOWER(author) = ?', [strtolower(trim($author))])
            ->first();
    }

    /**
     * Determine if the item is older than the configured ILL threshold.
     */
    public function isOlderThanIllThreshold(): bool
    {
        $year = $this->exact_publish_date?->year
            ?? (is_numeric($this->publish_date) ? (int) $this->publish_date : null);

        if (! $year) {
            return false;
        }

        $threshold = Setting::get('ill_age_threshold_years', 2);
        return (now()->year - $year) > $threshold;
    }

    /**
     * Check if a given year string exceeds the ILL threshold.
     */
    public static function yearExceedsIllThreshold(?string $year): bool
    {
        if (! $year || ! is_numeric($year)) {
            return false;
        }

        $threshold = Setting::get('ill_age_threshold_years', 2);
        return (now()->year - (int) $year) > $threshold;
    }
}
