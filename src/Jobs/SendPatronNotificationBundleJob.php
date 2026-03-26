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
 * Queued patron notification bundle (one or more {@see RequestMail} sends to the same address).
 *
 * Used for status-change templates and ILL conversion templates so the web request can return while
 * SMTP runs in a worker. Activity history matches the synchronous {@see NotificationService} wording
 * (including optional subject line for the legacy single-template path).
 *
 * @phpstan-type MessageShape array{subject: string, body: string}
 */
class SendPatronNotificationBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<MessageShape>  $messages
     * @param  string  $contextLabel  e.g. ' for status "Pending"' or ' (converted to interlibrary loan)'
     * @param  bool  $includeSubjectWhenSingle  Legacy default-template path logs subject when exactly one mail is sent.
     */
    public function __construct(
        public readonly int $requestId,
        public readonly string $patronEmail,
        public readonly string $contextLabel,
        public readonly array $messages,
        public readonly bool $includeSubjectWhenSingle = false,
    ) {}

    /**
     * Delivers each message via {@see Mail::send()} and records patron email activity when at least one succeeds.
     */
    public function handle(): void
    {
        $request = PatronRequest::query()->find($this->requestId);
        if (! $request || $this->messages === []) {
            return;
        }

        $sentCount = 0;
        foreach ($this->messages as $message) {
            $subject = $message['subject'] ?? '';
            $body    = $message['body'] ?? '';
            try {
                Mail::to($this->patronEmail)->send(new RequestMail($subject, $body));
                $sentCount++;
            } catch (\Throwable $e) {
                Log::error('Patron notification email failed', [
                    'patron_id'  => $request->patron_id,
                    'request_id' => $this->requestId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($sentCount === 0) {
            return;
        }

        $patronEmail = $this->patronEmail;
        $ctx         = $this->contextLabel;

        if ($sentCount === 1) {
            $note = 'Patron notification sent to ' . $patronEmail . $ctx . '.';
            if ($this->includeSubjectWhenSingle) {
                $subj = (string) ($this->messages[0]['subject'] ?? '');
                if ($subj !== '') {
                    $note .= ' Subject: ' . Str::limit($subj, 120);
                }
            }
        } else {
            $note = "Patron notification: {$sentCount} emails sent to {$patronEmail}{$ctx}.";
        }

        $request->fresh()?->logNotificationActivity(RequestStatusHistory::ACTIVITY_PATRON_EMAIL, $note);
    }
}
