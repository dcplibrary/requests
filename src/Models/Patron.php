<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patron extends Model
{
    protected $fillable = [
        'barcode',
        'name_first',
        'name_last',
        'phone',
        'email',
        'found_in_polaris',
        'polaris_lookup_attempted',
        'polaris_lookup_at',
        'polaris_patron_id',
        'polaris_patron_code_id',
        'polaris_name_first',
        'polaris_name_last',
        'polaris_phone',
        'polaris_email',
        'name_first_matches',
        'name_last_matches',
        'phone_matches',
        'email_matches',
        'preferred_phone',
        'preferred_email',
    ];

    protected $casts = [
        'found_in_polaris' => 'boolean',
        'polaris_lookup_attempted' => 'boolean',
        'polaris_lookup_at' => 'datetime',
        'name_first_matches' => 'boolean',
        'name_last_matches' => 'boolean',
        'phone_matches' => 'boolean',
        'email_matches' => 'boolean',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(SfpRequest::class);
    }

    /**
     * Patrons this patron has explicitly marked as not-a-duplicate.
     */
    public function ignoredDuplicates(): BelongsToMany
    {
        return $this->belongsToMany(
            Patron::class,
            'patron_ignored_duplicates',
            'patron_id',
            'ignored_patron_id'
        );
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name_first} {$this->name_last}";
    }

    /**
     * The canonical phone to use — 'polaris' or 'submitted' (default).
     */
    public function getEffectivePhoneAttribute(): ?string
    {
        return $this->preferred_phone === 'polaris'
            ? ($this->polaris_phone ?: $this->phone)
            : $this->phone;
    }

    /**
     * The canonical email to use — 'polaris' or 'submitted' (default).
     */
    public function getEffectiveEmailAttribute(): ?string
    {
        return $this->preferred_email === 'polaris'
            ? ($this->polaris_email ?: $this->email)
            : $this->email;
    }

    /**
     * Count requests within the configured rate-limit window.
     */
    public function recentRequestCount(): int
    {
        $window = Setting::get('sfp_limit_window', 'day');

        $since = match ($window) {
            'week'  => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        return $this->requests()->where('created_at', '>=', $since)->count();
    }

    /**
     * Whether this patron has hit their submission limit.
     */
    public function hasReachedLimit(): bool
    {
        $limit = Setting::get('sfp_limit_count', 5);
        return $this->recentRequestCount() >= $limit;
    }

    /**
     * Apply Polaris data to match fields after lookup.
     */
    public function applyPolarisData(array $polarisData): void
    {
        $this->update([
            'found_in_polaris'      => true,
            'polaris_lookup_attempted' => true,
            'polaris_lookup_at'     => now(),
            'polaris_patron_id'     => $polarisData['PatronID'] ?? null,
            'polaris_patron_code_id'=> $polarisData['PatronCodeID'] ?? null,
            'polaris_name_first'    => $polarisData['NameFirst'] ?? null,
            'polaris_name_last'     => $polarisData['NameLast'] ?? null,
            'polaris_phone'         => $polarisData['PhoneVoice1'] ?? null,
            'polaris_email'         => $polarisData['EmailAddress'] ?? null,
            'name_first_matches'    => $this->normalizeForCompare($this->name_first) === $this->normalizeForCompare($polarisData['NameFirst'] ?? ''),
            'name_last_matches'     => $this->normalizeForCompare($this->name_last) === $this->normalizeForCompare($polarisData['NameLast'] ?? ''),
            'phone_matches'         => $this->normalizePhone($this->phone) === $this->normalizePhone($polarisData['PhoneVoice1'] ?? ''),
            'email_matches'         => strtolower(trim($this->email ?? '')) === strtolower(trim($polarisData['EmailAddress'] ?? '')),
        ]);
    }

    /**
     * Mark Polaris lookup as attempted but not found.
     */
    public function markPolarisNotFound(): void
    {
        $this->update([
            'found_in_polaris' => false,
            'polaris_lookup_attempted' => true,
            'polaris_lookup_at' => now(),
        ]);
    }

    private function normalizeForCompare(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
}
