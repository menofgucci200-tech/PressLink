<?php

namespace App\Actions\Pressings;

use App\Enums\PressingRole;
use App\Http\Controllers\SitePageController;
use App\Mail\PressingSignedUp;
use App\Models\Pressing;
use App\Models\SalesAgent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Inscription d'un pressing, depuis le site ou par un commercial : le compte
 * du gérant (propriétaire), le pressing (services et rôles de base créés
 * d'office) et son mois offert. Le pressing est rattaché au commercial qui
 * l'apporte (son code, ou son espace). L'équipe PressLink est prévenue par
 * e-mail pour pouvoir l'appeler et l'aider à démarrer.
 */
class SignUpPressing
{
    /**
     * @param  array{pressing_name: string, city: string, pressing_phone: string, owner_name: string, owner_phone: string, email?: string|null, password: string, address?: string|null}  $data  téléphones déjà normalisés (+225…)
     * @param  bool  $temporaryPassword  mot de passe donné par un commercial : le gérant le change à sa première connexion
     */
    public function handle(array $data, ?SalesAgent $salesAgent = null, bool $temporaryPassword = false): User
    {
        [$owner, $pressing] = DB::transaction(function () use ($data, $salesAgent, $temporaryPassword) {
            $owner = User::create([
                'name' => $data['owner_name'],
                'phone' => $data['owner_phone'],
                'email' => filled($data['email'] ?? null) ? $data['email'] : null,
                'password' => $data['password'],
                'must_change_password' => $temporaryPassword,
            ]);

            $pressing = Pressing::create([
                'name' => $data['pressing_name'],
                'phone' => $data['pressing_phone'],
                'city' => $data['city'],
                'address' => filled($data['address'] ?? null) ? $data['address'] : null,
                'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            ]);

            if ($salesAgent) {
                $pressing->attachToSalesAgent($salesAgent);
            }

            Subscription::startTrialFor($pressing);

            $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

            return [$owner, $pressing];
        });

        if (SitePageController::canSendMail()) {
            try {
                Mail::to(config('presslink.support.email'))->send(new PressingSignedUp($pressing, $owner));
            } catch (Throwable $e) {
                Log::warning('Inscription d\'un pressing : e-mail à l\'équipe non envoyé', ['pressing' => $pressing->id, 'error' => $e->getMessage()]);
            }
        }

        return $owner;
    }
}
