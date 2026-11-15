<?php

namespace App\Mail;

use App\Models\PickupRequest;
use App\Models\Pressing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte aux administrateurs du pressing : nouvelle demande de ramassage,
 * ou ramassage annulé par le client. Une demande ouverte à la zone part chez
 * chaque pressing de la commune, sans le nom complet ni le téléphone du
 * client : ils n'apparaissent qu'au pressing qui l'accepte.
 */
class PickupRequestAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PickupRequest $pickupRequest, public bool $cancelled = false, public ?Pressing $recipient = null) {}

    public function envelope(): Envelope
    {
        if ($this->pickupRequest->isOpenToZone()) {
            return new Envelope(subject: "Ramassage à prendre à {$this->pickupRequest->commune} — {$this->pickupRequest->scheduleLabel()}");
        }

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
            'pressing' => $this->recipient ?? $this->pickupRequest->pressing,
            'openToZone' => $this->pickupRequest->isOpenToZone(),
        ]);
    }
}
