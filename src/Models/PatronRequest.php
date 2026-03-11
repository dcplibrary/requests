<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A patron's Suggest for Purchase request.
 *
 * Tracks the full lifecycle of a suggestion: submitted data, catalog and ISBNdb
 * search outcomes, duplicate detection, and status workflow. Both the raw
 * patron-entered values (`submitted_*`) and the resolved material record
 * (`material_id`) are stored so staff can compare what was submitted versus
 * what was matched.
 *
 * @property int              $id
 * @property int              $patron_id
 * @property int|null         $material_id
 * @property int              $request_status_id
 * @property string           $request_kind
 * @property string           $submitted_title
 * @property string           $submitted_author
 * @property string|null      $submitted_publish_date
 * @property string|null      $other_material_text
 * @property bool             $ill_requested
 * @property bool             $catalog_searched
 * @property int|null         $catalog_result_count
 * @property bool|null        $catalog_match_accepted
 * @property string|null      $catalog_match_bib_id
 * @property bool             $isbndb_searched
 * @property int|null         $isbndb_result_count
 * @property bool|null        $isbndb_match_accepted
 * @property bool             $is_duplicate
 * @property int|null         $duplicate_of_request_id
 * @property int|null         $assigned_to_user_id
 * @property \Carbon\Carbon|null $assigned_at
 * @property int|null         $assigned_by_user_id
 */
class PatronRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'patron_id',
        'material_id',
        'request_status_id',
        'request_kind',
        'submitted_title',
        'submitted_author',
        'submitted_publish_date',
        'other_material_text',
        'ill_requested',
        'catalog_searched',
        'catalog_result_count',
        'catalog_match_accepted',
        'catalog_match_bib_id',
        'isbndb_searched',
        'isbndb_result_count',
        'isbndb_match_accepted',
        'is_duplicate',
        'duplicate_of_request_id',
        'assigned_to_user_id',
        'assigned_at',
        'assigned_by_user_id',
    ];

    protected $casts = [
        'ill_requested' => 'boolean',
        'catalog_searched' => 'boolean',
        'catalog_match_accepted' => 'boolean',
        'isbndb_searched' => 'boolean',
        'isbndb_match_accepted' => 'boolean',
        'is_duplicate' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function scopeKind(\Illuminate\Database\Eloquent\Builder $query, string $kind): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('request_kind', $kind);
    }

    public function patron(): BelongsTo
    {
        return $this->belongsTo(Patron::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'request_status_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id')->orderBy('created_at');
    }

    /** All EAV field values for this request (material_type, audience, genre, etc.). */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(RequestFieldValue::class, 'request_id');
    }

    /**
     * Get a single field value by field key.
     *
     * @param  string  $fieldKey  e.g. 'material_type', 'audience', 'genre'
     * @return string|null
     */
    public function fieldValue(string $fieldKey): ?string
    {
        return $this->fieldValues
            ->first(fn (RequestFieldValue $v) => $v->field && $v->field->key === $fieldKey)
            ?->value;
    }

    /**
     * Get the display label for a field value.
     *
     * For select/radio fields the stored value is the option slug; this resolves
     * it to the human-readable FieldOption name. Results are cached in a static
     * array so repeated calls (e.g. in a list view) do not trigger extra queries.
     *
     * @param  string  $fieldKey  e.g. 'material_type', 'audience'
     * @return string|null
     */
    public function fieldValueLabel(string $fieldKey): ?string
    {
        $slug = $this->fieldValue($fieldKey);
        if ($slug === null) {
            return null;
        }

        /** @var array<string, string> */
        static $cache = [];
        $cacheKey = $fieldKey . '|' . $slug;

        if (! isset($cache[$cacheKey])) {
            $cache[$cacheKey] = FieldOption::whereHas('field', fn ($q) => $q->where('key', $fieldKey))
                ->where('slug', $slug)
                ->value('name') ?? $slug;
        }

        return $cache[$cacheKey];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(PatronRequest::class, 'duplicate_of_request_id');
    }

    /**
     * Update the status and log the history entry.
     */
    public function transitionStatus(int $statusId, ?int $userId = null, ?string $note = null): void
    {
        $this->update(['request_status_id' => $statusId]);

        $this->statusHistory()->create([
            'request_status_id' => $statusId,
            'user_id' => $userId,
            'note' => $note,
        ]);
    }

    /**
     * Scope: only requests the given user is authorized to see based on group membership.
     *
     * Accepts any authenticated user object (host app model or package model), or null
     * for unauthenticated requests (returns no rows).
     *
     * Resolution order:
     *  1. null user → no rows
     *  2. Already a Dcplibrary\Requests\Models\User → use its isAdmin() / group methods directly
     *  3. Any other Authenticatable → look up the matching staff user by email to get role/groups
     *  4. No matching staff user found → if APP_ENV=local show all (dev convenience), else no rows
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, ?Authenticatable $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        // Resolve to the staff User model so we always have isAdmin() / group methods
        if ($user instanceof \Dcplibrary\Requests\Models\User) {
            $staffUser = $user;
        } else {
            $staffUser = \Dcplibrary\Requests\Models\User::where('email', $user->email ?? '')->first();
        }

        if ($staffUser === null) {
            // No staff user record for this authenticated user.
            // In local dev, show all so you can test without a full user setup.
            if (app()->environment('local')) {
                return $query;
            }
            return $query->whereRaw('1 = 0');
        }

        if ($staffUser->isAdmin()) {
            return $query;
        }

        // Open access mode: any staff user can see all requests.
        if (Setting::get('requests_visibility_open_access', false)) {
            return $query;
        }

        // Assignment override: when enabled, assignees can always see their assigned requests.
        // Implement as an OR clause around the entire scoped access predicate.
        $assignmentEnabled = (bool) Setting::get('assignment_enabled', false);
        if ($assignmentEnabled) {
            $userId = $staffUser->getKey();
            return $query->where(function ($q) use ($staffUser, $userId) {
                $q->where('assigned_to_user_id', $userId)
                  ->orWhere(function ($q2) use ($staffUser) {
                      $this->applyScopedAccess($q2, $staffUser);
                  });
            });
        }

        $this->applyScopedAccess($query, $staffUser);
        return $query;
    }

    /**
     * Apply scoped access predicate (no open access; no assignment override).
     *
     * SFP requests: the user must belong to a selector group whose field options
     * cover both the request's material_type AND audience values (stored in
     * request_field_values). We look up the field IDs by key once, then use
     * correlated subqueries against the EAV table.
     */
    private function applyScopedAccess(\Illuminate\Database\Eloquent\Builder $query, \Dcplibrary\Requests\Models\User $staffUser): void
    {
        $illGroupId = (int) Setting::get('ill_selector_group_id', 0);

        // Resolve field IDs for the two scoping fields.
        // Use the Field model (not the DB facade) so the query goes through the
        // same Eloquent connection — works in both full Laravel and Capsule tests.
        $materialTypeFieldId = Field::where('key', 'material_type')->value('id');
        $audienceFieldId     = Field::where('key', 'audience')->value('id');

        $query->where(function ($q) use ($staffUser, $illGroupId, $materialTypeFieldId, $audienceFieldId) {
            // ILL: only members of the ILL group.
            $q->where(function ($ill) use ($staffUser, $illGroupId) {
                $ill->where('request_kind', 'ill');
                if ($illGroupId > 0) {
                    $ill->whereExists(function ($sub) use ($staffUser, $illGroupId) {
                        $sub->selectRaw('1')
                            ->from('selector_group_user as sgu_ill')
                            ->where('sgu_ill.selector_group_id', $illGroupId)
                            ->where('sgu_ill.user_id', $staffUser->getKey());
                    });
                } else {
                    $ill->whereRaw('1 = 0');
                }
            })
            // SFP: optionally strict selector-group pairing via field options.
            ->orWhere(function ($sfp) use ($staffUser, $materialTypeFieldId, $audienceFieldId) {
                $sfp->where('request_kind', 'sfp');

                if (! Setting::get('requests_visibility_strict_groups', true)) {
                    return;
                }

                $userId = $staffUser->getKey();
                $sfp->whereExists(function ($sub) use ($userId, $materialTypeFieldId, $audienceFieldId) {
                    $sub->selectRaw('1')
                        ->from('selector_group_user as sgu')
                        // Material type match
                        ->join('selector_group_field_option as sgfo_mt', 'sgfo_mt.selector_group_id', '=', 'sgu.selector_group_id')
                        ->join('field_options as fo_mt', function ($join) use ($materialTypeFieldId) {
                            $join->on('fo_mt.id', '=', 'sgfo_mt.field_option_id')
                                ->where('fo_mt.field_id', $materialTypeFieldId);
                        })
                        ->join('request_field_values as rfv_mt', function ($join) use ($materialTypeFieldId) {
                            $join->whereColumn('rfv_mt.request_id', 'requests.id')
                                ->where('rfv_mt.field_id', $materialTypeFieldId)
                                ->whereColumn('rfv_mt.value', 'fo_mt.slug');
                        })
                        // Audience match
                        ->join('selector_group_field_option as sgfo_aud', function ($join) {
                            $join->on('sgfo_aud.selector_group_id', '=', 'sgu.selector_group_id');
                        })
                        ->join('field_options as fo_aud', function ($join) use ($audienceFieldId) {
                            $join->on('fo_aud.id', '=', 'sgfo_aud.field_option_id')
                                ->where('fo_aud.field_id', $audienceFieldId);
                        })
                        ->join('request_field_values as rfv_aud', function ($join) use ($audienceFieldId) {
                            $join->whereColumn('rfv_aud.request_id', 'requests.id')
                                ->where('rfv_aud.field_id', $audienceFieldId)
                                ->whereColumn('rfv_aud.value', 'fo_aud.slug');
                        })
                        ->where('sgu.user_id', $userId);
                });
            });
        });
    }
}
