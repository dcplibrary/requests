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
 * @property string           $submitted_title
 * @property string           $submitted_author
 * @property string|null      $submitted_publish_date
 * @property string|null      $other_material_text
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
        'submitted_title',
        'submitted_author',
        'submitted_publish_date',
        'other_material_text',
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
    ];

    protected $casts = [
        'ill_requested' => 'boolean',
        'catalog_searched' => 'boolean',
        'catalog_match_accepted' => 'boolean',
        'isbndb_searched' => 'boolean',
        'isbndb_match_accepted' => 'boolean',
        'is_duplicate' => 'boolean',
    ];

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

        $materialTypeIds = $sfpUser->accessibleMaterialTypeIds();
        $audienceIds     = $sfpUser->accessibleAudienceIds();

        return $query->where(function ($q) use ($materialTypeIds, $audienceIds) {
            $q->whereIn('material_type_id', $materialTypeIds)
              ->whereIn('audience_id', $audienceIds);
        });
    }
}
