<?php

namespace App\Mail;

use App\Models\CnpjRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CnpjCorrectionRequested extends Mailable
{
    public function __construct(public CnpjRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Parafa CNPJ: solicitação de alteração — '.$this->request->cnpj,
            replyTo: [new Address($this->request->email, $this->request->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.cnpj-correction-requested');
    }
}
