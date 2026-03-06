<?php

namespace Dcplibrary\Sfp\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Generic SFP mailable.
 *
 * Subject and HTML body are passed in at construction time after all
 * placeholder replacements have already been applied by NotificationService.
 * This keeps the mailable thin and reusable for every notification type.
 */
class SfpMail extends Mailable
{
    public function __construct(
        private string $emailSubject,
        private string $emailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        $logoPath = dirname(__DIR__, 2) . '/resources/images/dcpl-logo.png';
        $logoSrc  = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        return new Content(
            view: 'sfp::mail.sfp',
            with: [
                'body'    => $this->emailBody,
                'logoSrc' => $logoSrc,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
