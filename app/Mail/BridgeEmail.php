<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BridgeEmail extends Mailable
{
    /**
     * @param  array<int, array{filename?: string, content?: string, mime?: string}>  $emailAttachments
     */
    public function __construct(
        public string $emailSubject,
        public string $htmlBody,
        public array $emailAttachments = [],
        public ?string $replyToAddress = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
            replyTo: $this->replyToAddress !== null && $this->replyToAddress !== ''
                ? [$this->replyToAddress]
                : [],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->emailAttachments as $attachment) {
            // Decodifica lenient: se il contenuto non è base64 valido viene
            // allegato così com'è (stesso comportamento della vecchia app).
            $content = base64_decode($attachment['content'] ?? '', true);

            if ($content === false) {
                $content = $attachment['content'] ?? '';
            }

            $attachments[] = Attachment::fromData(fn (): string => $content, $attachment['filename'] ?? 'allegato')
                ->withMime($attachment['mime'] ?? 'application/octet-stream');
        }

        return $attachments;
    }
}
