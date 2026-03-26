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

/**
 * Queued staff workflow mail (reroute, convert-to-ILL, etc.) to all resolved routing recipients.
 *
 * Same subject/body for every address; one combined history line listing successful recipients.
 */
class SendStaffWorkflowMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  int  $requestId  Primary key of {@see PatronRequest}
     * @param  string  $action  Human-readable label for history (e.g. "Rerouted")
     * @param  list<string>  $recipients  Staff emails (same set as built in {@see NotificationService::notifyStaffWorkflowAction()})
     */
    public function __construct(
        public readonly int $requestId,
        public readonly string $action,
        public readonly array $recipients,
        public readonly string $subject,
        public readonly string $body,
    ) {}

    /**
     * Sends to each recipient; logs {@see RequestStatusHistory::ACTIVITY_STAFF_WORKFLOW} if any succeed.
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
                Mail::to($email)->send(new RequestMail($this->subject, $this->body));
                $sentTo[] = $email;
            } catch (\Throwable $e) {
                Log::error('Staff workflow notification failed', [
                    'to'         => $email,
                    'request_id' => $this->requestId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($sentTo === []) {
            return;
        }

        $request->fresh()?->logNotificationActivity(
            RequestStatusHistory::ACTIVITY_STAFF_WORKFLOW,
            'Workflow notification (“' . $this->action . '”) sent to: ' . implode(', ', $sentTo)
        );
    }
}
