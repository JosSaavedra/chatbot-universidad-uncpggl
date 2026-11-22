<?php

namespace App\Mail;

use App\Models\QrAuthRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class LoginApprovalMail extends Mailable
{
    public function __construct(public QrAuthRequest $approval, public string $reviewUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirma tu acceso al chatbot UNCPGGL');
    }

    public function content(): Content
    {
        return new Content(view: 'qr-auth.email-message');
    }
}
