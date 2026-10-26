<?php

namespace App\Http\Controllers;

use App\Support\LaunchOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Page d'accueil publique de presslink.org (référencement) : présente
 * l'application client et le logiciel de gestion. Un utilisateur déjà
 * connecté est renvoyé directement vers son espace.
 */
class LandingController extends Controller
{
    /**
     * Questions affichées sur /questions-frequentes ET reprises à l'identique dans les
     * données structurées FAQPage (Google exige que les deux concordent).
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(): array
    {
        return [
            [
                'question' => 'Comment suivre ma commande de pressing avec PressLink ?',
                'answer' => 'Ouvrez l\'onglet Commandes de l\'application : chaque commande affiche son avancement (reçue, en traitement, prête, récupérée) et son détail. Vous recevez aussi une notification quand elle est prête.',
            ],
            [
                'question' => 'L\'application PressLink est-elle gratuite ?',
                'answer' => 'Oui, l\'application est gratuite pour les clients des pressings. Les pressings utilisent PressLink comme logiciel de gestion'
                    .(LaunchOffer::isActive() ? ', gratuit lui aussi pendant le lancement officiel, jusqu\'au '.LaunchOffer::dateLabel().'.' : '.'),
            ],
            [
                'question' => 'Comment installer PressLink sur mon téléphone ?',
                'answer' => 'Ouvrez presslink.org/app ou scannez le code QR. Sur Android, appuyez sur « Installer ». Sur iPhone, ouvrez le lien dans Safari, touchez Partager puis « Sur l\'écran d\'accueil ». Aucun téléchargement depuis un store n\'est nécessaire.',
            ],
            [
                'question' => 'Comment retrouver mes commandes et mon pressing dans l\'application ?',
                'answer' => 'Créez votre compte avec le numéro de téléphone que vous donnez au pressing : vos commandes s\'affichent automatiquement, dans tous vos pressings PressLink. Aucun code n\'est nécessaire.',
            ],
            [
                'question' => 'Je suis propriétaire de pressing : à quoi sert PressLink ?',
                'answer' => 'PressLink est un logiciel de gestion de pressing : enregistrement et suivi des commandes, fiches clients, services et tarifs, gestion de l\'équipe, tableau de bord, exports CSV, Excel et PDF, et notifications automatiques envoyées à vos clients.',
            ],
            [
                'question' => 'Comment contacter le support PressLink ?',
                'answer' => 'Appelez ou écrivez sur WhatsApp au '.config('presslink.support.phone_display').', ou utilisez « Aide & support » dans le menu de l\'application.',
            ],
        ];
    }

    public function __invoke(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(Auth::user()->is_super_admin ? '/admin' : route('dashboard'));
        }

        $baseUrl = rtrim((string) config('app.url'), '/');

        return view('landing', [
            'baseUrl' => $baseUrl,
            'appUrl' => $baseUrl.'/app/',
            'supportPhoneDisplay' => config('presslink.support.phone_display'),
            'supportPhoneE164' => config('presslink.support.phone_e164'),
        ]);
    }
}
