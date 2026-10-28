<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\SitePageController;
use App\Mail\TrialEndingSoon;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Rappel de fin d'essai gratuit : un e-mail aux propriétaires du pressing
 * (ceux qui ont une adresse) 3 jours puis 1 jour avant la fin, avec le lien
 * pour choisir et payer sa formule.
 */
class SendTrialReminders extends Command
{
    protected $signature = 'presslink:trial-reminders';

    protected $description = 'Prévient les pressings 3 jours puis 1 jour avant la fin de leur essai gratuit.';

    /** Jours restants qui déclenchent un rappel. */
    public const DAYS_BEFORE = [3, 1];

    public function handle(): int
    {
        if (! SitePageController::canSendMail()) {
            $this->info('Envoi d\'e-mails désactivé : aucun rappel.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach (self::DAYS_BEFORE as $days) {
            $day = now()->addDays($days);

            Subscription::query()
                ->where('status', SubscriptionStatus::Trialing->value)
                ->whereBetween('trial_ends_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->with('pressing.admins')
                ->get()
                ->each(function (Subscription $subscription) use ($days, &$sent) {
                    foreach ($subscription->pressing?->admins ?? [] as $owner) {
                        if (blank($owner->email)) {
                            continue;
                        }
                        try {
                            Mail::to($owner->email)->send(new TrialEndingSoon($subscription, $owner, $days));
                            $sent++;
                        } catch (Throwable $e) {
                            Log::warning('Rappel de fin d\'essai non envoyé', ['subscription' => $subscription->id, 'error' => $e->getMessage()]);
                        }
                    }
                });
        }

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
