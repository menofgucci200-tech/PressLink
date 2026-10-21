<?php

namespace App\Livewire\Subscription;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\SitePageController;
use App\Livewire\Concerns\ShowsToasts;
use App\Mail\SubscriptionRequested;
use App\Models\Pressing;
use App\Models\SubscriptionRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Page Abonnement du dashboard pressing (RB-09) : formule, commandes du
 * mois, fonctions incluses et options. Le pressing DEMANDE une option, des
 * commandes en plus ou une autre formule ; l'équipe PressLink encaisse hors
 * application puis active la demande depuis la fiche du pressing.
 */
class Show extends Component
{
    use ShowsToasts;

    /** Option mise en avant (arrivée depuis une page réservée). */
    #[Url(except: '')]
    public string $option = '';

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::SubscriptionManage, $pressing), 403);
    }

    private function pressing(): Pressing
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::SubscriptionManage, $pressing) && $pressing->subscription, 403);

        return $pressing;
    }

    public function requestOption(string $feature): void
    {
        $pressing = $this->pressing();
        $feature = Feature::tryFrom($feature);
        abort_if($feature === null, 422);

        if ($pressing->subscription->hasFeature($feature)) {
            return;
        }

        $this->createRequest($pressing, ['kind' => SubscriptionRequest::KIND_OPTION, 'feature' => $feature]);
    }

    public function requestOrders(int $count): void
    {
        $pressing = $this->pressing();
        abort_unless(in_array($count, SubscriptionRequest::ORDER_PACKS, true), 422);

        $this->createRequest($pressing, ['kind' => SubscriptionRequest::KIND_ORDERS, 'extra_orders' => $count]);
    }

    public function requestPlan(string $plan): void
    {
        $pressing = $this->pressing();
        $plan = SubscriptionPlan::tryFrom($plan);
        abort_if($plan === null || $plan === $pressing->subscription->plan, 422);

        $this->createRequest($pressing, ['kind' => SubscriptionRequest::KIND_PLAN, 'plan' => $plan]);
    }

    public function cancelRequest(int $requestId): void
    {
        $request = $this->pressing()->subscriptionRequests()->pending()->findOrFail($requestId);
        $request->delete();
        $this->toast('Demande annulée.');
    }

    /** @param  array<string, mixed>  $attributes */
    private function createRequest(Pressing $pressing, array $attributes): void
    {
        $alreadyPending = $pressing->subscriptionRequests()->pending()
            ->where('kind', $attributes['kind'])
            ->when(isset($attributes['feature']), fn ($q) => $q->where('feature', $attributes['feature']->value))
            ->exists();

        if ($alreadyPending && $attributes['kind'] !== SubscriptionRequest::KIND_ORDERS) {
            $this->toast('Cette demande est déjà envoyée : l\'équipe PressLink vous recontacte.', 'info');

            return;
        }

        $request = $pressing->subscriptionRequests()->create([...$attributes, 'requested_by' => auth()->id(), 'status' => SubscriptionRequest::PENDING]);

        if (SitePageController::canSendMail()) {
            try {
                Mail::to(config('presslink.support.email'))->send(new SubscriptionRequested($request));
            } catch (Throwable $e) {
                Log::warning('Demande d\'abonnement : e-mail non envoyé', ['request' => $request->id, 'error' => $e->getMessage()]);
            }
        }

        $this->toast('Demande envoyée : l\'équipe PressLink vous appelle pour le paiement, puis l\'active.');
    }

    #[Layout('layouts.dashboard', ['active' => 'subscription', 'title' => 'Abonnement'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();
        $subscription = $pressing->subscription;

        return view('livewire.subscription.show', [
            'subscription' => $subscription,
            'features' => Feature::cases(),
            'plans' => SubscriptionPlan::cases(),
            'orderPacks' => SubscriptionRequest::ORDER_PACKS,
            'pendingRequests' => $pressing->subscriptionRequests()->pending()->latest()->get(),
        ]);
    }
}
