<?php

namespace App\Livewire\Admin\Plans;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Support\PlanCatalog;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formules et prix — Super Admin : prix mensuel et annuel, commandes par
 * mois et fonctions incluses de chaque formule ; durée de l'essai gratuit ;
 * prix des options et des commandes en plus.
 */
class Index extends Component
{
    use ShowsToasts;

    /** @var array<string, array{monthly: string, annual: string, orders_limit: string, features: list<string>}> */
    public array $plans = [];

    public string $trialDays = '';

    /** Offre de lancement affichée avec les formules (vide = aucune). */
    public string $launchOffer = '';

    /** Bloquer les nouvelles commandes après la fin de la période payée (+ délai de grâce). */
    public bool $blockAfterPeriodEnd = false;

    /** @var array<string, string> fonction => prix mensuel de l'option */
    public array $optionPrices = [];

    /** @var list<array{count: string, price: string}> */
    public array $orderPacks = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->fillFromSettings();
    }

    private function fillFromSettings(): void
    {
        $text = fn (?int $value) => $value !== null ? (string) $value : '';

        foreach (SubscriptionPlan::cases() as $plan) {
            $settings = $plan->settings();
            $this->plans[$plan->value] = [
                'monthly' => $text($settings['monthly']),
                'annual' => $text($settings['annual']),
                'orders_limit' => $text($settings['orders_limit']),
                'features' => $settings['features'],
            ];
        }

        $this->trialDays = (string) Subscription::trialDays();
        $this->launchOffer = (string) PlanCatalog::launchOffer();
        $this->blockAfterPeriodEnd = Subscription::blocksAfterPeriodEnd();
        foreach (Feature::cases() as $feature) {
            $this->optionPrices[$feature->value] = $text($feature->priceFcfa());
        }
        $this->orderPacks = array_map(fn (array $pack) => ['count' => (string) $pack['count'], 'price' => $text($pack['price'])], SubscriptionRequest::orderPacks());
    }

    public function addPack(): void
    {
        $this->orderPacks[] = ['count' => '', 'price' => ''];
    }

    public function removePack(int $index): void
    {
        unset($this->orderPacks[$index]);
        $this->orderPacks = array_values($this->orderPacks);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->validate([
            'plans.*.monthly' => ['nullable', 'integer', 'min:0'],
            'plans.*.annual' => ['nullable', 'integer', 'min:0'],
            'plans.*.orders_limit' => ['nullable', 'integer', 'min:1'],
            'plans.*.features' => ['array'],
            'plans.*.features.*' => [Rule::enum(Feature::class)],
            'trialDays' => ['required', 'integer', 'min:0', 'max:365'],
            'launchOffer' => ['nullable', 'string', 'max:200'],
            'blockAfterPeriodEnd' => ['boolean'],
            'optionPrices.*' => ['nullable', 'integer', 'min:0'],
            'orderPacks' => ['array'],
            'orderPacks.*.count' => ['required', 'integer', 'min:1', 'distinct'],
            'orderPacks.*.price' => ['nullable', 'integer', 'min:0'],
        ], [
            'orderPacks.*.count.required' => 'Indiquez un nombre de commandes.',
            'orderPacks.*.count.distinct' => 'Deux lignes ont le même nombre de commandes.',
        ]);

        $number = fn (string $value) => $value === '' ? null : (int) $value;
        $before = collect(SubscriptionPlan::cases())->mapWithKeys(fn (SubscriptionPlan $plan) => [$plan->value => $plan->ordersLimit()]);

        Setting::write('plans', collect(SubscriptionPlan::cases())->mapWithKeys(fn (SubscriptionPlan $plan) => [$plan->value => [
            'monthly' => $number($this->plans[$plan->value]['monthly']),
            'annual' => $number($this->plans[$plan->value]['annual']),
            'orders_limit' => $number($this->plans[$plan->value]['orders_limit']),
            'features' => array_values(array_intersect(array_column(Feature::cases(), 'value'), $this->plans[$plan->value]['features'] ?? [])),
        ]])->all());
        Setting::write('trial_days', (int) $this->trialDays);
        Setting::write('launch_offer', trim($this->launchOffer));
        Setting::write('block_after_period_end', $this->blockAfterPeriodEnd);
        Setting::write('option_prices', array_map($number, $this->optionPrices));
        Setting::write('order_packs', collect($this->orderPacks)
            ->map(fn (array $pack) => ['count' => (int) $pack['count'], 'price' => $number($pack['price'])])
            ->sortBy('count')->values()->all());

        // Les pressings qui suivaient le nombre de commandes de leur formule
        // passent au nouveau ; ceux réglés à la main gardent le leur.
        $moved = 0;
        foreach (SubscriptionPlan::cases() as $plan) {
            $old = $before[$plan->value];
            $new = $plan->ordersLimit();
            if ($old === $new) {
                continue;
            }
            $moved += Subscription::query()
                ->whereIn('plan', $this->storedValues($plan))
                ->when($old === null, fn ($q) => $q->whereNull('orders_limit'), fn ($q) => $q->where('orders_limit', $old))
                ->update(['orders_limit' => $new]);
        }

        $this->fillFromSettings();
        $this->toast('Formules enregistrées.'.($moved > 0 ? " Nombre de commandes mis à jour pour {$moved} pressing(s)." : ''));
    }

    /** @return list<string> valeurs en base de cette formule (anciennes comprises) */
    private function storedValues(SubscriptionPlan $plan): array
    {
        return match ($plan) {
            SubscriptionPlan::Business => ['business', 'pro'],
            SubscriptionPlan::Premium => ['premium', 'enterprise'],
            default => [$plan->value],
        };
    }

    #[Layout('layouts.admin', ['active' => 'plans', 'title' => 'Formules'])]
    public function render()
    {
        $counts = Subscription::query()->selectRaw('plan, count(*) as total')->groupBy('plan')->pluck('total', 'plan');

        return view('livewire.admin.plans.index', [
            'planCases' => SubscriptionPlan::cases(),
            'features' => Feature::cases(),
            'pressingsPerPlan' => collect(SubscriptionPlan::cases())->mapWithKeys(fn (SubscriptionPlan $plan) => [
                $plan->value => collect($this->storedValues($plan))->sum(fn (string $value) => (int) ($counts[$value] ?? 0)),
            ]),
        ]);
    }
}
