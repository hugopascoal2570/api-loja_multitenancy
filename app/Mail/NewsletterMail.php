<?php

namespace App\Mail;

use App\Models\Newsletter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $newsletterTitle;
    public string $newsletterContent;
    public ?string $imageUrl;
    public string $unsubscribeUrl;

    public function __construct(Newsletter $newsletter, string $unsubscribeUrl)
    {
        $this->newsletterTitle = $newsletter->title;
        $this->newsletterContent = $newsletter->content;
        $this->imageUrl = $newsletter->image_path
            ? Storage::disk('public')->url($newsletter->image_path)
            : null;
        $this->unsubscribeUrl = $unsubscribeUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->newsletterTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
        );
    }
}
