<?php

namespace Dcplibrary\Requests\Jobs;

use Dcplibrary\Requests\Mail\RequestMail;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Models\RequestStatusHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Queued staff routing send for a single selector group.
 *
 * Mirrors {@see NotificationService::notifyStaffNewRequest()} per-group behavior: one history line
 * after all recipients for that group succeed. Failed addresses are logged; the job does not fail
 * the queue unless the worker throws outside the per-recipient try/catch.
 */
class SendStaffRoutingGroupMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<string>  $recipients  Distinct recipient addresses for this group send
     */
    public function __construct(
        public readonly int $requestId,
        public readonly int $selectorGroupId,
        public readonly string $groupName,
        public readonly array $recipients,
        public readonly string $subject,
        public readonly string $body,
    ) {}

    /**
     * Sends {@see RequestMail} to each recipient and writes one staff-routing activity entry if any succeed.
     */
    public function handle(): void
    {
        $request = PatronRequest::query()->find($this->requestId);
        if (! $request) {
            return;
        }

        $sentTo = [];
        foreach ($this->recipients as $email) {
            try {
                Mail::to($email)->send(new RequestMail($this->subject, $this->body, 'staff'));
                $sentTo[] = $email;
            } catch (\Throwable $e) {
                Log::error('Staff routing email failed', [
                    'to'         => $email,
                    'request_id' => $this->requestId,
                    'group_id'   => $this->selectorGroupId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($sentTo === []) {
            return;
        }

        $request->fresh()?->logNotificationActivity(
            RequestStatusHistory::ACTIVITY_STAFF_ROUTING,
            'Staff routing email to ' . implode(', ', $sentTo)
                . ' (group: ' . ($this->groupName !== '' ? $this->groupName : '—') . '). Subject: ' . Str::limit($this->subject, 140)
        );
    }
}
