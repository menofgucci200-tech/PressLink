<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\OneSignalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Base commune aux notifications de commande — Cahier §9.
 * MVP : canal push (OneSignalChannel) + persistance en base pour la liste
 * "Notifications" de l'app client.
 *
 * ShouldQueueAfterCommit (pas juste ShouldQueue) : l'écriture en base et
 * l'appel push sont déportés sur un worker de queue au lieu de s'exécuter
 * dans la requête HTTP qui déclenche le changement de statut (voir
 * load-testing/RAPPORT.md, finding A). "AfterCommit" est indispensable ici
 * : ces notifications sont envoyées depuis OrderObserver, DANS la
 * transaction DB de CreateOrderAction — avec un simple ShouldQueue (et
 * config('queue.connections.database.after_commit') = false par défaut),
 * le job serait poussé sur la queue AVANT que la commande ne soit
 * committée, et pourrait référencer une commande qui n'existe pas encore
 * (ou plus, si la transaction est finalement annulée).
 */
abstract class OrderNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(protected Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        // Pressing de test : clients fictifs, rien ne part (ni push, ni e-mail).
        if ($this->order->pressing?->isSandbox()) {
            return [];
        }

        $channels = ['database', OneSignalChannel::class];

        if ($this->sendsEmail() && method_exists($notifiable, 'wantsOrderEmails') && $notifiable->wantsOrderEmails()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Doublon email des étapes clés (créée, prête), en complément du push.
     */
    protected function sendsEmail(): bool
    {
        return false;
    }

    /** Phrase d'introduction de l'email. */
    protected function emailIntro(): string
    {
        // Le texte de l'e-mail est inséré tel quel : tout ce qui vient d'une saisie (nom du pressing…) est échappé.
        return e($this->body());
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $this->order->loadMissing(['items', 'pressing']);

        return (new MailMessage)
            ->subject($this->title().' — '.$this->order->order_number)
            ->markdown('mail.order-update', [
                'title' => $this->title(),
                'intro' => $this->emailIntro(),
                'order' => $this->order,
                'pressing' => $this->order->pressing,
                'customer' => $notifiable,
                'unsubscribe' => URL::signedRoute('emails.unsubscribe', ['customer' => $notifiable->getKey()]),
            ]);
    }

    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'pressing_name' => $this->order->pressing->name,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toOneSignal(mixed $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'data' => [
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
