<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use SerializesModels;

    public string $resetUrl;
    public string $email;
    public string $userName;

    public function __construct(string $resetUrl, string $email, string $userName)
    {
        $this->resetUrl  = $resetUrl;
        $this->email     = $email;
        $this->userName  = $userName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperação de Senha - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'resetUrl' => $this->resetUrl,
                'email'    => $this->email,
                'name'     => $this->userName,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
