@props(['plans', 'mode' => 'site', 'current' => null, 'pendingPlans' => []])
{{--
    Cartes des formules (App\Support\PlanCatalog), communes à la page Tarifs
    du site (mode « site ») et à la page Formules de l'espace pressing (mode
    « espace » : formule actuelle, demande de changement).
--}}
@php
    $fcfa = fn (?int $amount) => \App\Support\PlanCatalog::fcfa($amount);
    $check = '<svg class="w-4 h-4 flex-none mt-0.5 text-(--color-primary)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    $whatsapp = 'https://wa.me/'.ltrim((string) config('presslink.support.phone_e164'), '+').'?text=';
    $rank = fn (\App\Enums\SubscriptionPlan $plan) => array_search($plan, \App\Enums\SubscriptionPlan::cases(), true);
@endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-1 lg:grid-cols-3 gap-5 items-stretch']) }}>
    @foreach ($plans as $item)
        @php
            $plan = $item['plan'];
            $isCurrent = $current === $plan;
            $highlight = $mode === 'espace' ? $isCurrent : $item['recommended'];
        @endphp
        <article data-plan="{{ $plan->value }}"
                 class="relative flex flex-col rounded-2xl border bg-(--color-surface) p-6 {{ $highlight ? 'border-(--color-primary) ring-2 ring-(--color-primary)/15 shadow-lg shadow-blue-900/5' : 'border-(--color-border)' }}">
            @if ($mode === 'espace' && $isCurrent)
                <span class="absolute -top-3 left-6 inline-flex items-center h-6 px-3 rounded-full bg-(--color-primary) text-white text-[11px] font-semibold uppercase tracking-wide">Votre formule</span>
            @elseif ($mode === 'site' && $item['recommended'])
                <span class="absolute -top-3 left-6 inline-flex items-center h-6 px-3 rounded-full bg-(--color-primary) text-white text-[11px] font-semibold uppercase tracking-wide">Recommandée</span>
            @endif

            <h3 class="font-display text-2xl font-bold tracking-tight">{{ $item['label'] }}</h3>
            <p class="text-sm font-semibold text-(--color-primary) mt-0.5">{{ $item['tagline'] }}</p>
            <p class="text-sm text-(--color-text-secondary) mt-2 min-h-10">{{ $item['audience'] }}</p>

            <div class="mt-5">
                @if ($item['monthly'] !== null)
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-display text-4xl font-extrabold tracking-tight tabular-nums">{{ $fcfa($item['monthly']) }}</span>
                        <span class="text-sm text-(--color-text-secondary)">F / mois</span>
                    </div>
                    @if ($item['annual'] !== null)
                        <p class="text-xs text-(--color-text-secondary) mt-1">ou {{ $fcfa($item['annual']) }} F par an</p>
                    @endif
                @else
                    <span class="font-display text-3xl font-extrabold tracking-tight">Sur devis</span>
                @endif
            </div>

            <p class="mt-4 flex items-center gap-2 text-sm font-medium">
                <svg class="w-4 h-4 text-(--color-text-muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                {{ $item['ordersLimit'] === null ? 'Commandes illimitées' : $fcfa($item['ordersLimit']).' commandes par mois' }}
            </p>

            {{-- Action --}}
            <div class="mt-5">
                @if ($mode === 'espace')
                    @if ($isCurrent)
                        <div class="h-11 rounded-xl bg-(--color-bg) text-(--color-text-secondary) text-sm font-semibold flex items-center justify-center">Votre formule actuelle</div>
                    @elseif (in_array($plan->value, $pendingPlans, true) && \App\Support\GeniusPay::isConfigured())
                        <button type="button" wire:click="requestPlan('{{ $plan->value }}', $wire.cycle ?? 'monthly')" wire:loading.attr="disabled"
                                class="w-full h-11 rounded-xl text-sm font-semibold bg-(--color-primary) text-white hover:bg-(--color-primary-600)">
                            Finir le paiement
                        </button>
                    @elseif (in_array($plan->value, $pendingPlans, true))
                        <div class="h-11 rounded-xl bg-(--color-info-tint) text-(--color-info) text-sm font-semibold flex items-center justify-center">Demande envoyée</div>
                    @else
                        @php $upgrade = $current === null || $rank($plan) > $rank($current); @endphp
                        <button type="button" wire:click="requestPlan('{{ $plan->value }}', $wire.cycle ?? 'monthly')" wire:loading.attr="disabled"
                                class="w-full h-11 rounded-xl text-sm font-semibold transition-colors {{ $upgrade ? 'bg-(--color-primary) text-white hover:bg-(--color-primary-600)' : 'border border-(--color-border) hover:border-(--color-primary) hover:text-(--color-primary)' }}">
                            {{ $upgrade ? 'Passer à '.$item['label'] : 'Choisir '.$item['label'] }}
                        </button>
                    @endif
                @else
                    <a href="{{ $whatsapp.rawurlencode("Bonjour, je souhaite démarrer PressLink avec la formule {$item['label']} pour mon pressing.") }}" target="_blank" rel="noopener"
                       class="w-full h-11 rounded-xl text-sm font-semibold flex items-center justify-center transition-colors {{ $item['recommended'] ? 'bg-(--color-primary) text-white hover:bg-(--color-primary-600)' : 'border border-(--color-border) hover:border-(--color-primary) hover:text-(--color-primary)' }}">
                        Choisir {{ $item['label'] }}
                    </a>
                @endif
            </div>

            {{-- Ce que la formule contient --}}
            <div class="mt-6 pt-5 border-t border-(--color-border) flex-1">
                @if ($item['previous'] === null)
                    <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">L'essentiel</p>
                    <ul class="space-y-2.5 text-sm">
                        @foreach (\App\Support\PlanCatalog::ESSENTIALS as $essential)
                            <li class="flex items-start gap-2.5">{!! $check !!}<span>{{ $essential }}</span></li>
                        @endforeach
                        @foreach ($item['added'] as $feature)
                            <li class="flex items-start gap-2.5">{!! $check !!}<span>{{ $feature->label() }}</span></li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Tout {{ $item['previous']->label() }}, plus :</p>
                    <ul class="space-y-3 text-sm">
                        @forelse ($item['added'] as $feature)
                            <li class="flex items-start gap-2.5">{!! $check !!}
                                <span><span class="font-semibold">{{ $feature->label() }}</span><br><span class="text-(--color-text-secondary)">{{ $feature->description() }}</span></span>
                            </li>
                        @empty
                            <li class="text-(--color-text-secondary)">Plus de commandes chaque mois.</li>
                        @endforelse
                    </ul>
                @endif
            </div>
        </article>
    @endforeach
</div>
