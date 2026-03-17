<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bibliographic item referenced by one or more patron requests.
 *
 * Materials are created from three sources (tracked via `source`):
 * - `submitted` — patron-entered data only, no external match found
 * - `isbndb`    — enriched from ISBNdb API match accepted by the patron
 * - `polaris`   — enriched from ILS data (future)
 *
 * Duplicate materials (same normalized title + author) are surfaced in the
 * Titles admin view and can be merged by staff.
 *
 * @property int                   $id
 * @property string                $title
 * @property string                $author
 * @property string|null           $publish_date            Flexible string (e.g. "2022", "January 2022")
 * @property string|null           $isbn
 * @property string|null           $isbn13
 * @property string|null           $publisher
 * @property \Carbon\Carbon|null   $exact_publish_date
 * @property string|null           $edition
 * @property string|null           $overview
 * @property string|null           $title_long              Full title with subtitle (ISBNdb)
 * @property string|null           $synopsis                Synopsis text (ISBNdb)
 * @property array|null            $subjects                Subject headings (ISBNdb)
 * @property string|null           $dewey_decimal           Dewey Decimal classification (ISBNdb)
 * @property int|null              $pages                   Page count (ISBNdb)
 * @property string|null           $language                Language code, e.g. "eng" (ISBNdb)
 * @property float|null            $msrp                    List price / MSRP (ISBNdb)
 * @property string|null           $binding                 e.g. "Hardcover", "Paperback" (ISBNdb)
 * @property string|null           $dimensions              Physical dimensions (ISBNdb)
 * @property string                $source                  'submitted'|'isbndb'|'polaris'
 * @property int|null              $material_type_option_id FK→field_options (material type)
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
        'title_long',
        'synopsis',
        'subjects',
        'dewey_decimal',
        'pages',
        'language',
        'msrp',
        'binding',
        'dimensions',
        'source',
        'material_type_option_id',
    ];

    protected $casts = [
        'exact_publish_date' => 'date',
        'msrp'               => 'decimal:2',
        'subjects'           => 'array',
    ];

    /** The material type option from the unified field_options table. */
    public function materialTypeOption(): BelongsTo
    {
        return $this->belongsTo(FieldOption::class, 'material_type_option_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PatronRequest::class);
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
     *  2. Already a Dcplibrary\Requests\Models\User → use directly
     *  3. Any other Authenticatable → look up staff user by email
     *  4. No staff user found → if APP_ENV=local show all, else no rows
     *  5. Admin → all rows
     *  6. Selector → filter by accessible material_type_ids
     */
    public function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user instanceof \Dcplibrary\Requests\Models\User) {
            $staffUser = $user;
        } else {
            $staffUser = \Dcplibrary\Requests\Models\User::where('email', $user->email ?? '')->first();
        }

        if ($staffUser === null) {
            if (app()->environment('local')) {
                return $query;
            }
            return $query->whereRaw('1 = 0');
        }

        if ($staffUser->isAdmin()) {
            return $query;
        }

        $materialTypeOptionIds = $staffUser->accessibleFieldOptionIds('material_type');

        return $query->whereIn('material_type_option_id', $materialTypeOptionIds);
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
