<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CnpjRequestConfirmation extends Mailable
{
    public function __construct(public string $confirmationUrl, public string $protocol) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme sua solicitação — Parafa CNPJ');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.cnpj-request-confirmation');
    }
}
