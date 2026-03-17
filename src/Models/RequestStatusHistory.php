<?php

namespace Dcplibrary\Requests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit log entry recording a status transition for a patron request.
 *
 * Created by `PatronRequest::transitionStatus()`. The `user_id` is null for
 * system-initiated transitions (e.g. automatic status set on submission).
 *
 * @property int         $id
 * @property int         $request_id
 * @property int         $request_status_id
 * @property int|null    $user_id           Null for system transitions
 * @property string|null $note
 * @property string|null $activity_type  Non-null for email notification log rows (not a status change)
 */
class RequestStatusHistory extends Model
{
    public const ACTIVITY_STAFF_ROUTING = 'staff_routing';

    public const ACTIVITY_PATRON_EMAIL = 'patron_email';

    public const ACTIVITY_STAFF_ASSIGNEE = 'staff_assignee';

    public const ACTIVITY_STAFF_WORKFLOW = 'staff_workflow';

    protected $table = 'request_status_history';

    protected $fillable = ['request_id', 'request_status_id', 'user_id', 'note', 'activity_type'];

    public static function activityTypeLabel(?string $type): string
    {
        return match ($type) {
            self::ACTIVITY_STAFF_ROUTING => 'Staff email (new request)',
            self::ACTIVITY_PATRON_EMAIL => 'Patron email (status update)',
            self::ACTIVITY_STAFF_ASSIGNEE => 'Staff email (assignment)',
            self::ACTIVITY_STAFF_WORKFLOW => 'Staff email (workflow)',
            default => $type ? 'Notification' : '',
        };
    }

    /** The request this history entry belongs to. */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PatronRequest::class, 'request_id');
    }

    /** The status set by this transition. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'request_status_id');
    }

    /** The staff user who made the change, or null for system transitions. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
