<?php

namespace App\Mail;

use App\Models\Pressing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Prévient l'équipe PressLink (config presslink.support.email) qu'un pressing
 * s'est inscrit seul depuis le site, pour l'appeler et l'aider à démarrer.
 */
class PressingSignedUp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pressing $pressing, public User $owner) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Inscription] '.$this->pressing->name.($this->pressing->city ? ' — '.$this->pressing->city : ''),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pressing-signed-up');
    }
}
