<?php

namespace App\Mail;

use App\Models\OrderIssue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte envoyée aux administrateurs d'un pressing quand un client signale
 * un problème sur l'une de ses commandes.
 */
class OrderIssueReported extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrderIssue $issue) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Signalement sur la commande '.$this->issue->order->order_number.' — '.$this->issue->category->label(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-issue-reported', with: [
            'order' => $this->issue->order,
            'customer' => $this->issue->customer,
            'pressing' => $this->issue->order->pressing,
        ]);
    }
}
