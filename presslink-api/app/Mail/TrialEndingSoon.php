<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Rappel au propriétaire : l'essai gratuit de son pressing se termine bientôt. */
class TrialEndingSoon extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription, public User $owner, public int $daysLeft) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->daysLeft <= 1
                ? 'Votre essai PressLink se termine demain'
                : "Votre essai PressLink se termine dans {$this->daysLeft} jours",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.trial-ending-soon');
    }
}
