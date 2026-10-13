<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Message envoyé depuis le formulaire de la page Contact vers l'adresse
 * PressLink (config presslink.support.email). « Répondre » renvoie
 * directement à l'expéditeur s'il a laissé son email.
 */
class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: ?string, phone: ?string, profile: string, message: string}  $data
     */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Contact presslink.org] '.$this->data['profile'].' — '.$this->data['name'],
            replyTo: $this->data['email'] ? [new Address($this->data['email'], $this->data['name'])] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-message');
    }
}
