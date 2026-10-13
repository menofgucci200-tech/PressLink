<?php

namespace App\Livewire\Support;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Page Support du dashboard pressing : joindre l'équipe PressLink par appel
 * ou WhatsApp (message pré-rempli avec le nom du membre et du pressing),
 * plus quelques réponses aux questions courantes. Accessible à tout le staff.
 */
class Show extends Component
{
    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(): array
    {
        return [
            [
                'question' => 'Comment enregistrer une nouvelle commande ?',
                'answer' => 'Cliquez sur « Nouvelle commande » (Tableau de bord ou Commandes), choisissez le client, ajoutez les articles, puis confirmez. Le client reçoit automatiquement une notification.',
            ],
            [
                'question' => 'Comment faire avancer le statut d\'une commande ?',
                'answer' => 'Ouvrez la commande depuis la liste : les boutons d\'action permettent de la passer en traitement, prête, puis récupérée. Le client est notifié quand elle est prête.',
            ],
            [
                'question' => 'Comment ajouter un employé ?',
                'answer' => 'Allez dans « Équipe » (réservé aux administrateurs), cliquez sur « Ajouter un membre », puis choisissez son identifiant et son mot de passe et communiquez-les-lui. Le nombre de comptes dépend de votre formule.',
            ],
            [
                'question' => 'Comment exporter mes commandes ?',
                'answer' => 'Depuis le Tableau de bord ou la liste des Commandes, utilisez « Exporter » : CSV, Excel ou PDF, selon la période et les filtres choisis.',
            ],
            [
                'question' => 'Comment mes clients rejoignent-ils mon pressing ?',
                'answer' => 'Ils saisissent le code de votre pressing dans l\'application (il figure dans « Paramètres »), ou vous les créez directement depuis « Clients ».',
            ],
        ];
    }

    #[Layout('layouts.dashboard', ['active' => 'support', 'title' => 'Support'])]
    public function render()
    {
        $user = auth()->user();
        $pressing = $user->currentPressing();

        $who = $pressing
            ? "Bonjour, je suis {$user->name} ({$pressing->name}) et j'ai besoin d'aide avec PressLink."
            : "Bonjour, je suis {$user->name} et j'ai besoin d'aide avec PressLink.";

        $phoneE164 = (string) config('presslink.support.phone_e164');

        return view('livewire.support.show', [
            'phoneDisplay' => (string) config('presslink.support.phone_display'),
            'phoneE164' => $phoneE164,
            'whatsappUrl' => 'https://wa.me/'.ltrim($phoneE164, '+').'?text='.rawurlencode($who),
            'faqs' => self::faqs(),
        ]);
    }
}
