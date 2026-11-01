<?php

namespace Modules\Quality\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Quality\Models\QualityAlert;

class QualityAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *     heading: string,
     *     as_of: string,
     *     kind: string,
     *     status: ?string,
     *     badge: string,
     *     preamble: list<string>,
     *     sections: list<array{title: string, lines: list<string>, tone?: string}>
     * }  $payload
     */
    public function __construct(
        public QualityAlert $alert,
        public array $payload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->alert->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'quality::mail.quality-alert',
            with: [
                'alert' => $this->alert,
                'payload' => $this->payload,
                'url' => url('/admin/quality/alerts'),
            ],
        );
    }
}
