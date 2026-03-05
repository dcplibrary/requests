<?php

namespace Dcplibrary\Sfp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A library patron who has submitted at least one SFP request.
 *
 * Patrons are identified by their library card barcode. On first submission a
 * `LookupPatronInPolaris` job is queued to validate and enrich the record against
 * the ILS. Match fields flag discrepancies for staff review.
 *
 * @property int              $id
 * @property string           $barcode
 * @property string           $name_first
 * @property string           $name_last
 * @property string           $phone
 * @property string|null      $email
 * @property bool             $found_in_polaris
 * @property bool             $polaris_lookup_attempted
 * @property \Carbon\Carbon|null $polaris_lookup_at
 * @property int|null         $polaris_patron_id
 * @property int|null         $polaris_patron_code_id
 * @property string|null      $polaris_name_first
 * @property string|null      $polaris_name_last
 * @property string|null      $polaris_phone
 * @property string|null      $polaris_email
 * @property bool|null        $name_first_matches
 * @property bool|null        $name_last_matches
 * @property bool|null        $phone_matches
 * @property bool|null        $email_matches
 * @property string           $preferred_phone  'submitted'|'polaris'
 * @property string           $preferred_email  'submitted'|'polaris'
 */
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

    /** All SFP requests submitted by this patron. */
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

    /** Computed full name: "{first} {last}". */
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
        return $this->requests()
            ->where('created_at', '>=', $this->windowStart())
            ->count();
    }

    /**
     * Whether this patron has hit their submission limit.
     */
    public function hasReachedLimit(): bool
    {
        $limit = (int) Setting::get('sfp_limit_count', 5);
        return $this->recentRequestCount() >= $limit;
    }

    /**
     * The earliest date this patron can submit again.
     * For rolling windows this is tied to the oldest request in the window;
     * for calendar windows it is the fixed start of the next period.
     */
    public function nextAvailableDate(): ?Carbon
    {
        $type = Setting::get('sfp_limit_window_type', 'rolling');

        if ($type === 'calendar_month') {
            return $this->nextCalendarMonthStart();
        }

        if ($type === 'calendar_week') {
            return now()->addWeek()->startOfWeek(Carbon::MONDAY)->startOfDay();
        }

        // Rolling: oldest request in the window + window length
        $days   = (int) Setting::get('sfp_limit_window_days', 30);
        $oldest = $this->requests()
            ->where('created_at', '>=', $this->windowStart())
            ->oldest()
            ->first();

        return $oldest ? $oldest->created_at->copy()->addDays($days) : null;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * The start of the current limit window, accounting for the window type.
     */
    private function windowStart(): Carbon
    {
        $type = Setting::get('sfp_limit_window_type', 'rolling');

        return match ($type) {
            'calendar_month' => $this->calendarMonthStart(),
            'calendar_week'  => now()->startOfWeek(Carbon::MONDAY)->startOfDay(),
            default          => now()->subDays((int) Setting::get('sfp_limit_window_days', 30)),
        };
    }

    /**
     * Start of the current calendar-month period, based on the configured reset day.
     */
    private function calendarMonthStart(): Carbon
    {
        $resetDay = max(1, min(28, (int) Setting::get('sfp_limit_calendar_reset_day', 1)));
        $now      = now();

        return $now->day >= $resetDay
            ? $now->copy()->day($resetDay)->startOfDay()
            : $now->copy()->subMonthNoOverflow()->day($resetDay)->startOfDay();
    }

    /**
     * Start of the next calendar-month period.
     */
    private function nextCalendarMonthStart(): Carbon
    {
        $resetDay = max(1, min(28, (int) Setting::get('sfp_limit_calendar_reset_day', 1)));
        $now      = now();

        return $now->day >= $resetDay
            ? $now->copy()->addMonthNoOverflow()->day($resetDay)->startOfDay()
            : $now->copy()->day($resetDay)->startOfDay();
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
