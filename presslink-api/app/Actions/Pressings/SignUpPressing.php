<?php

namespace App\Actions\Pressings;

use App\Enums\PressingRole;
use App\Http\Controllers\SitePageController;
use App\Mail\PressingSignedUp;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Inscription d'un pressing depuis le site, sans passer par l'équipe : le
 * compte du gérant (propriétaire), le pressing (services et rôles de base
 * créés d'office) et son essai gratuit. L'équipe PressLink est prévenue
 * par e-mail pour pouvoir l'appeler et l'aider à démarrer.
 */
class SignUpPressing
{
    /**
     * @param  array{pressing_name: string, city: string, pressing_phone: string, owner_name: string, owner_phone: string, email?: string|null, password: string}  $data  téléphones déjà normalisés (+225…)
     */
    public function handle(array $data): User
    {
        [$owner, $pressing] = DB::transaction(function () use ($data) {
            $owner = User::create([
                'name' => $data['owner_name'],
                'phone' => $data['owner_phone'],
                'email' => filled($data['email'] ?? null) ? $data['email'] : null,
                'password' => $data['password'],
            ]);

            $pressing = Pressing::create([
                'name' => $data['pressing_name'],
                'phone' => $data['pressing_phone'],
                'city' => $data['city'],
                'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            ]);

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
