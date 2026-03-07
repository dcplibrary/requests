<?php

namespace Dcplibrary\Sfp\Models;

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
 * @property int|null         $audience_id
 * @property int|null         $material_type_id
 * @property int              $request_status_id
 * @property string           $request_kind
 * @property string           $submitted_title
 * @property string           $submitted_author
 * @property string|null      $submitted_publish_date
 * @property string|null      $other_material_text
 * @property string|null      $genre
 * @property string|null      $where_heard
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
class SfpRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'patron_id',
        'material_id',
        'audience_id',
        'material_type_id',
        'request_status_id',
        'request_kind',
        'submitted_title',
        'submitted_author',
        'submitted_publish_date',
        'other_material_text',
        'genre',
        'where_heard',
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

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'request_status_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id')->orderBy('created_at');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(RequestCustomFieldValue::class, 'request_id');
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
        return $this->belongsTo(SfpRequest::class, 'duplicate_of_request_id');
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
     *  2. Already a Dcplibrary\Sfp\Models\User → use its isAdmin() / group methods directly
     *  3. Any other Authenticatable → look up the matching SFP user by email to get role/groups
     *  4. No matching SFP user found → if APP_ENV=local show all (dev convenience), else no rows
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, ?Authenticatable $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        // Resolve to the SFP User model so we always have isAdmin() / group methods
        if ($user instanceof \Dcplibrary\Sfp\Models\User) {
            $sfpUser = $user;
        } else {
            $sfpUser = \Dcplibrary\Sfp\Models\User::where('email', $user->email ?? '')->first();
        }

        if ($sfpUser === null) {
            // No SFP user record for this authenticated user.
            // In local dev, show all so you can test without a full user setup.
            if (app()->environment('local')) {
                return $query;
            }
            return $query->whereRaw('1 = 0');
        }

        if ($sfpUser->isAdmin()) {
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
            $userId = $sfpUser->getKey();
            return $query->where(function ($q) use ($sfpUser, $userId) {
                $q->where('assigned_to_user_id', $userId)
                  ->orWhere(function ($q2) use ($sfpUser) {
                      $this->applyScopedAccess($q2, $sfpUser);
                  });
            });
        }

        $this->applyScopedAccess($query, $sfpUser);
        return $query;
    }

    /**
     * Apply scoped access predicate (no open access; no assignment override).
     */
    private function applyScopedAccess(\Illuminate\Database\Eloquent\Builder $query, \Dcplibrary\Sfp\Models\User $sfpUser): void
    {
        // ILL requests: gated by ILL access group membership.
        $illGroupId = (int) Setting::get('ill_selector_group_id', 0);

        $query->where(function ($q) use ($sfpUser, $illGroupId) {
            // ILL: only members of the ILL group.
            $q->where(function ($ill) use ($sfpUser, $illGroupId) {
                $ill->where('request_kind', 'ill');
                if ($illGroupId > 0) {
                    $ill->whereExists(function ($sub) use ($sfpUser, $illGroupId) {
                        $sub->selectRaw('1')
                            ->from('selector_group_user as sgu_ill')
                            ->where('sgu_ill.selector_group_id', $illGroupId)
                            ->where('sgu_ill.user_id', $sfpUser->getKey());
                    });
                } else {
                    // No configured ILL group → deny ILL access by default.
                    $ill->whereRaw('1 = 0');
                }
            })
            // SFP: optionally strict selector-group pairing.
            ->orWhere(function ($sfp) use ($sfpUser) {
                $sfp->where('request_kind', 'sfp');

                if (! Setting::get('requests_visibility_strict_groups', true)) {
                    return;
                }

                $userId = $sfpUser->getKey();
                $sfp->whereExists(function ($sub) use ($userId) {
                    $sub->selectRaw('1')
                        ->from('selector_group_user as sgu')
                        ->join('selector_group_material_type as sgmt', function ($join) {
                            $join->on('sgmt.selector_group_id', '=', 'sgu.selector_group_id')
                                ->whereColumn('sgmt.material_type_id', 'requests.material_type_id');
                        })
                        ->join('selector_group_audience as sga', function ($join) {
                            $join->on('sga.selector_group_id', '=', 'sgu.selector_group_id')
                                ->whereColumn('sga.audience_id', 'requests.audience_id');
                        })
                        ->where('sgu.user_id', $userId);
                });
            });
        });
    }
}
