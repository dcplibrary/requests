<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
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
     * Scope: only materials the given user is authorized to see based on group membership.
     *
     * Materials are filtered by material type only (they carry no audience).
     * Admins see everything. Selectors see only materials whose material_type_id
     * falls within their assigned SelectorGroup material types.
     *
     * Resolution order:
     *  1. null user → no rows
     *  2. Already a Dcplibrary\Sfp\Models\User → use directly
     *  3. Any other Authenticatable → look up SFP user by email
     *  4. No SFP user found → if APP_ENV=local show all, else no rows
     *  5. Admin → all rows
     *  6. Selector → filter by accessible material_type_ids
     */
    public function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user instanceof \Dcplibrary\Sfp\Models\User) {
            $sfpUser = $user;
        } else {
            $sfpUser = \Dcplibrary\Sfp\Models\User::where('email', $user->email ?? '')->first();
        }

        if ($sfpUser === null) {
            if (app()->environment('local')) {
                return $query;
            }
            return $query->whereRaw('1 = 0');
        }

        if ($sfpUser->isAdmin()) {
            return $query;
        }

        $materialTypeIds = $sfpUser->accessibleMaterialTypeIds();

        return $query->whereIn('material_type_id', $materialTypeIds);
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
