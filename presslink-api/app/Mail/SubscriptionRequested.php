<?php

namespace App\Mail;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Prévient l'équipe PressLink (config presslink.support.email) qu'un
 * pressing demande une option, des commandes en plus ou une autre formule.
 */
class SubscriptionRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SubscriptionRequest $subscriptionRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Abonnement] '.$this->subscriptionRequest->pressing->name.' — '.$this->subscriptionRequest->label(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.subscription-requested');
    }
}
