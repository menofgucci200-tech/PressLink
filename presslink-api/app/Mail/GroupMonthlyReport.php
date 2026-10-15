<?php

namespace App\Mail;

use App\Models\User;
use App\Support\GroupReport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Rapport mensuel du groupe envoyé au propriétaire, PDF en pièce jointe. */
class GroupMonthlyReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Carbon $month) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'PressLink · Rapport du groupe · '.ucfirst($this->month->translatedFormat('F Y')));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.group-monthly-report', with: GroupReport::data($this->user, $this->month));
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => GroupReport::pdf($this->user, $this->month)->output(), GroupReport::filename($this->month))
                ->withMime('application/pdf'),
        ];
    }
}
