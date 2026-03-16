<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A patron's Suggest for Purchase or Interlibrary Loan request.
 *
 * Tracks the full lifecycle: submitted data, catalog and ISBNdb search outcomes,
 * duplicate detection, and status workflow. Both the raw patron-entered values
 * (`submitted_*`) and the resolved material record (`material_id`) are stored
 * so staff can compare what was submitted versus what was matched.
 *
 * Request kind is one of KIND_SFP or KIND_ILL. Use kinds() for a list of valid kinds.
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
 * @property bool             $notify_by_email  Patron opted in to email notifications at time of submission
 * @property int|null         $assigned_to_user_id
 * @property \Carbon\Carbon|null $assigned_at
 * @property int|null         $assigned_by_user_id
 */
class PatronRequest extends Model
{
    /** Request kind: Suggest for Purchase. */
    public const KIND_SFP = 'sfp';

    /** Request kind: Interlibrary Loan. */
    public const KIND_ILL = 'ill';

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
        'notify_by_email',
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
        'notify_by_email' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    /**
     * All valid request kind slugs.
     *
     * @return array<int, string>  List of kind slugs (e.g. for validation or iteration).
     */
    public static function kinds(): array
    {
        return [self::KIND_SFP, self::KIND_ILL];
    }

    /**
     * Scope: filter by request kind.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $kind  One of {@see PatronRequest::KIND_SFP} or {@see PatronRequest::KIND_ILL}.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeKind(\Illuminate\Database\Eloquent\Builder $query, string $kind): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('request_kind', $kind);
    }

    /**
     * Scope: only requests that have a given value for a field (by field key).
     *
     * Used for staff index filters (material_type, audience, custom filter).
     * When $kind is provided, only fields applicable to that kind are considered.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $fieldKey  Field key (e.g. 'material_type', 'audience').
     * @param  string  $value    Stored value (e.g. option slug).
     * @param  string|null  $kind  Optional request kind to restrict the field lookup ({@see PatronRequest::KIND_SFP}, {@see PatronRequest::KIND_ILL}).
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereFieldValue(
        \Illuminate\Database\Eloquent\Builder $query,
        string $fieldKey,
        string $value,
        ?string $kind = null
    ): \Illuminate\Database\Eloquent\Builder {
        $fieldId = Field::query()
            ->where('key', $fieldKey)
            ->when($kind, fn ($q) => $q->forKind($kind))
            ->value('id');

        if (! $fieldId) {
            return $query;
        }

        return $query->whereExists(function ($sub) use ($fieldId, $value) {
            $sub->selectRaw('1')
                ->from('request_field_values')
                ->whereColumn('request_field_values.request_id', 'requests.id')
                ->where('request_field_values.field_id', $fieldId)
                ->where('request_field_values.value', $value);
        });
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
     * cover ALL of the request's filterable field values (stored in
     * request_field_values). Filterable fields are discovered dynamically — any
     * select/radio field marked `filterable` that has at least one option
     * assigned to a selector group will be checked.
     *
     * If a group has no options for a given filterable field, that field is
     * treated as unrestricted (open) for that group.
     */
    private function applyScopedAccess(\Illuminate\Database\Eloquent\Builder $query, \Dcplibrary\Requests\Models\User $staffUser): void
    {
        $illGroupId = (int) Setting::get('ill_selector_group_id', 0);

        $query->where(function ($q) use ($staffUser, $illGroupId) {
            // ILL: only members of the ILL group.
            $q->where(function ($ill) use ($staffUser, $illGroupId) {
                $ill->where('request_kind', self::KIND_ILL);
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
            ->orWhere(function ($sfp) use ($staffUser) {
                $sfp->where('request_kind', self::KIND_SFP);

                if (! Setting::get('requests_visibility_strict_groups', true)) {
                    return;
                }

                $this->applySfpFieldScoping($sfp, $staffUser);
            });
        });
    }

    /**
     * Build dynamic field-based scoping joins for SFP requests.
     *
     * Requires the user to belong to a single selector group that covers
     * ALL of the request's filterable field values simultaneously (no
     * "bridging" across groups). If a group has no options for a given
     * field, that field is treated as unrestricted for that group.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $sfp
     * @param  User  $staffUser
     * @return void
     */
    private function applySfpFieldScoping(\Illuminate\Database\Eloquent\Builder $sfp, User $staffUser): void
    {
        // Discover filterable fields that actually have options assigned to groups.
        $scopingFields = Field::query()
            ->where('filterable', true)
            ->whereIn('type', ['select', 'radio'])
            ->where('active', true)
            ->whereHas('options', function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('selector_group_field_option')
                        ->whereColumn('selector_group_field_option.field_option_id', 'field_options.id');
                });
            })
            ->get(['id', 'key']);

        if ($scopingFields->isEmpty()) {
            // No filterable fields have group-assigned options → no SFP access for selectors.
            $sfp->whereRaw('1 = 0');
            return;
        }

        $userId = $staffUser->getKey();

        // One outer EXISTS that anchors on a single group the user belongs to.
        // All field checks are correlated to sgu.selector_group_id so the same
        // group must satisfy every filterable field.
        $sfp->whereExists(function ($outer) use ($userId, $scopingFields) {
            $outer->selectRaw('1')
                ->from('selector_group_user as sgu')
                ->where('sgu.user_id', $userId);

            foreach ($scopingFields as $field) {
                $a = 'sf_' . $field->key;

                // For this field the group must either:
                //  (a) have an option matching the request's value, OR
                //  (b) have NO options for this field at all (unrestricted).
                $outer->where(function ($check) use ($field, $a) {
                    $check->whereExists(function ($sub) use ($field, $a) {
                        $sub->selectRaw('1')
                            ->from("selector_group_field_option as sgfo_{$a}")
                            ->whereColumn("sgfo_{$a}.selector_group_id", 'sgu.selector_group_id')
                            ->join("field_options as fo_{$a}", function ($j) use ($field, $a) {
                                $j->on("fo_{$a}.id", '=', "sgfo_{$a}.field_option_id")
                                  ->where("fo_{$a}.field_id", $field->id);
                            })
                            ->join("request_field_values as rfv_{$a}", function ($j) use ($field, $a) {
                                $j->whereColumn("rfv_{$a}.request_id", 'requests.id')
                                  ->where("rfv_{$a}.field_id", $field->id)
                                  ->whereColumn("rfv_{$a}.value", "fo_{$a}.slug");
                            });
                    })
                    ->orWhereNotExists(function ($sub) use ($field, $a) {
                        $sub->selectRaw('1')
                            ->from("selector_group_field_option as sgfo_no_{$a}")
                            ->whereColumn("sgfo_no_{$a}.selector_group_id", 'sgu.selector_group_id')
                            ->join("field_options as fo_no_{$a}", function ($j) use ($field, $a) {
                                $j->on("fo_no_{$a}.id", '=', "sgfo_no_{$a}.field_option_id")
                                  ->where("fo_no_{$a}.field_id", $field->id);
                            });
                    });
                });
            }
        });
    }
}
