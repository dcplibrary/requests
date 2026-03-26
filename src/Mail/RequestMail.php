<?php

namespace Dcplibrary\Requests\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Generic notification mailable.
 *
 * Subject and HTML body are passed in at construction time after all
 * placeholder replacements have already been applied by NotificationService.
 * This keeps the mailable thin and reusable for every notification type.
 */
class RequestMail extends Mailable
{
    /**
     * @param  string  $emailSubject  Final subject line after placeholder replacement
     * @param  string  $emailBody     Final HTML body after placeholder replacement
     */
    public function __construct(
        private string $emailSubject,
        private string $emailBody,
    ) {}

    /**
     * @return Envelope  Message envelope with the resolved subject
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    /**
     * @return Content  Blade view `requests::mail.notification` with body and logo URL
     */
    public function content(): Content
    {
        return new Content(
            view: 'requests::mail.notification',
            with: [
                'body'    => $this->emailBody,
                'logoSrc' => route('request.assets.logo'),
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>  No attachments for package notifications
     */
    public function attachments(): array
    {
        return [];
    }
}
