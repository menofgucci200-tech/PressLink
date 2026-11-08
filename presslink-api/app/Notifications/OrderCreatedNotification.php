<?php

namespace App\Notifications;

class OrderCreatedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Commande enregistrée';
    }

    public function body(): string
    {
        if ($this->order->details_pending) {
            return "Votre dépôt #{$this->order->order_number} est enregistré chez {$this->order->pressing->name} : {$this->order->declared_pieces} pièce(s). Le détail et le prix suivront.";
        }

        return "Votre commande #{$this->order->order_number} a été enregistrée chez {$this->order->pressing->name}.";
    }

    protected function sendsEmail(): bool
    {
        return true;
    }

    protected function emailIntro(): string
    {
        if ($this->order->details_pending) {
            return "Votre dépôt **{$this->order->order_number}** est bien enregistré chez **".e(str_replace(['*', '_', '[', ']'], '', $this->order->pressing->name)).'**. Le pressing saisira le détail et le prix après le tri ; nous vous préviendrons.';
        }

        return "Votre commande **{$this->order->order_number}** a bien été enregistrée chez **".e(str_replace(['*', '_', '[', ']'], '', $this->order->pressing->name))."**. Voici son récapitulatif ; nous vous préviendrons dès qu'elle sera prête.";
    }
}
