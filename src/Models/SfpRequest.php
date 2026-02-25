<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Accepts any authenticated user object (host app model or package model).
     * If the user has an isAdmin() method, it is used; otherwise the 'role' attribute
     * is checked directly. If the user has accessibleMaterialTypeIds() / accessibleAudienceIds()
     * methods (i.e. is a Dcplibrary\Sfp\Models\User), those are used for filtering;
     * otherwise admins see all and non-admins see nothing until those methods exist.
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, Authenticatable $user): \Illuminate\Database\Eloquent\Builder
    {
        $isAdmin = method_exists($user, 'isAdmin')
            ? $user->isAdmin()
            : (($user->role ?? null) === 'admin');

        if ($isAdmin) {
            return $query;
        }

        if (method_exists($user, 'accessibleMaterialTypeIds') && method_exists($user, 'accessibleAudienceIds')) {
            $materialTypeIds = $user->accessibleMaterialTypeIds();
            $audienceIds     = $user->accessibleAudienceIds();

            return $query->where(function ($q) use ($materialTypeIds, $audienceIds) {
                $q->whereIn('material_type_id', $materialTypeIds)
                  ->whereIn('audience_id', $audienceIds);
            });
        }

        // Fallback: non-admin users without group membership methods see nothing.
        return $query->whereRaw('1 = 0');
    }
}
