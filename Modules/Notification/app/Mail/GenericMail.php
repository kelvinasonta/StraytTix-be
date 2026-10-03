<?php

// app/Mail/GenericMail.php

namespace Modules\Notification\app\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $renderedSubject,
        public string $renderedBody,
        public ?string $replyToEmail = null,
        public ?string $replyToName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
            replyTo: $this->replyToEmail
                ? [new Address($this->replyToEmail, $this->replyToName ?? '')]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderedBody);
    }
}
