<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bibliographic item referenced by one or more SFP requests.
 *
 * Materials are created from three sources (tracked via `source`):
 * - `submitted` — patron-entered data only, no external match found
 * - `isbndb`    — enriched from ISBNdb API match accepted by the patron
 * - `polaris`   — enriched from ILS data (future)
 *
 * Duplicate materials (same normalized title + author) are surfaced in the
 * Titles admin view and can be merged by staff.
 *
 * @property int              $id
 * @property string           $title
 * @property string           $author
 * @property string|null      $publish_date       Flexible string (e.g. "2022", "January 2022")
 * @property string|null      $isbn
 * @property string|null      $isbn13
 * @property string|null      $publisher
 * @property \Carbon\Carbon|null $exact_publish_date
 * @property string|null      $edition
 * @property string|null      $overview
 * @property string           $source             'submitted'|'isbndb'|'polaris'
 * @property int|null         $material_type_id
 */
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
