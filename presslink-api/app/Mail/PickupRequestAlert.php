<?php

namespace App\Mail;

use App\Models\PickupRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte aux administrateurs du pressing : nouvelle demande de ramassage,
 * ou ramassage annulé par le client.
 */
class PickupRequestAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PickupRequest $pickupRequest, public bool $cancelled = false) {}

    public function envelope(): Envelope
    {
        $customer = $this->pickupRequest->customer->fullName();

        return new Envelope(
            subject: $this->cancelled
                ? "Ramassage annulé par {$customer}"
                : "Demande de ramassage de {$customer} — {$this->pickupRequest->scheduleLabel()}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pickup-request-alert', with: [
            'customer' => $this->pickupRequest->customer,
            'pressing' => $this->pickupRequest->pressing,
        ]);
    }
}
