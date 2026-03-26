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
 * Queued assignee notification after a request is reassigned.
 *
 * Body is already finalized (workflow header + staff routing template) by {@see NotificationService::notifyAssignee()}.
 */
class SendAssigneeNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  int  $requestId  Primary key of {@see PatronRequest}
     * @param  string  $assigneeEmail  Staff recipient
     * @param  string  $subject  Final subject after placeholder replacement
     * @param  string  $body  Final HTML body after placeholder replacement
     */
    public function __construct(
        public readonly int $requestId,
        public readonly string $assigneeEmail,
        public readonly string $subject,
        public readonly string $body,
    ) {}

    /**
     * Sends mail and logs {@see RequestStatusHistory::ACTIVITY_STAFF_ASSIGNEE} on success.
     */
    public function handle(): void
    {
        $request = PatronRequest::query()->find($this->requestId);
        if (! $request) {
            return;
        }

        try {
            Mail::to($this->assigneeEmail)->send(new RequestMail($this->subject, $this->body));
            $request->fresh()?->logNotificationActivity(
                RequestStatusHistory::ACTIVITY_STAFF_ASSIGNEE,
                'Assignee notification sent to ' . $this->assigneeEmail . '. Subject: ' . Str::limit($this->subject, 120)
            );
        } catch (\Throwable $e) {
            Log::error('Assignee notification failed', [
                'to'         => $this->assigneeEmail,
                'request_id' => $this->requestId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
