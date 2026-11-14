<?php

namespace App\Mail;

use App\Models\OrderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte aux administrateurs du pressing : un client demande la livraison
 * d'une commande prête, ou annule une livraison prévue.
 */
class DeliveryRequestAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrderDelivery $delivery, public bool $cancelled = false) {}

    public function envelope(): Envelope
    {
        $customer = $this->delivery->customer->fullName();

        return new Envelope(
            subject: $this->cancelled
                ? "Livraison annulée par {$customer}"
                : "Livraison demandée par {$customer} — {$this->delivery->scheduleLabel()}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.delivery-request-alert', with: [
            'customer' => $this->delivery->customer,
            'pressing' => $this->delivery->pressing,
            'order' => $this->delivery->order,
        ]);
    }
}
