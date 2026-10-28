@props(['pressing'])
{{--
    Guide de démarrage d'un nouveau pressing (tableau de bord) : les cinq
    étapes qui rendent l'outil utile au comptoir. Disparaît quand tout est fait,
    ou deux mois après la création du pressing. Réservé à qui règle le pressing.
--}}
@php
    $show = auth()->user()->may(\App\Enums\Permission::SettingsManage)
        && $pressing->created_at?->isAfter(now()->subDays(60));

    $steps = $show ? [
        [
            'done' => \App\Models\ServicePrice::whereIn('service_id', $pressing->services()->select('id'))->where('price_fcfa', '>', 0)->exists(),
            'title' => 'Ajoutez vos articles et vos prix',
            'text' => 'Chemise, pantalon, costume… et le prix de chaque service.',
            'url' => route('services.index', ['guide' => 'prix']),
        ],
        [
            'done' => ! empty($pressing->opening_hours),
            'title' => 'Indiquez vos horaires',
            'text' => 'Vos clients les voient dans l\'application.',
            'url' => route('pressing.settings', ['guide' => 'horaires']),
        ],
        [
            'done' => filled($pressing->logo_path),
            'title' => 'Ajoutez votre logo',
            'text' => 'Il apparaît sur vos reçus et dans l\'application.',
            'url' => route('pressing.settings', ['guide' => 'logo']),
        ],
        [
            'done' => $pressing->staff()->count() > 1,
            'title' => 'Invitez votre équipe',
            'text' => 'Chacun son accès : gérant, caissière, employé.',
            'url' => route('team.index', ['guide' => 'equipe']),
        ],
        [
            'done' => $pressing->orders()->exists(),
            'title' => 'Enregistrez votre première commande',
            'text' => 'Votre client est prévenu dès qu\'elle est prête.',
            'url' => route('orders.create', ['guide' => 'commande']),
        ],
    ] : [];

    $doneCount = collect($steps)->where('done', true)->count();
@endphp

@if ($show && $doneCount < count($steps))
    <section class="mb-6 bg-(--color-surface) border border-(--color-border) rounded-xl p-5" data-testid="onboarding-guide" aria-labelledby="guide-titre">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 id="guide-titre" class="font-display text-base font-bold">Bien démarrer avec PressLink</h2>
            <span class="text-sm text-(--color-text-muted) tabular-nums">{{ $doneCount }} sur {{ count($steps) }} étapes</span>
        </div>
        <div class="h-1.5 rounded-full bg-(--color-bg) overflow-hidden mb-4" aria-hidden="true">
            <div class="h-full rounded-full bg-(--color-primary)" style="width: {{ round($doneCount / count($steps) * 100) }}%"></div>
        </div>
        <ol class="divide-y divide-(--color-border)">
            @foreach ($steps as $step)
                <li class="flex items-center gap-3 py-2.5">
                    <span @class([
                        'flex-none w-6 h-6 rounded-full flex items-center justify-center',
                        'bg-(--color-success-tint) text-(--color-success-text)' => $step['done'],
                        'border-2 border-(--color-border)' => ! $step['done'],
                    ]) aria-hidden="true">
                        @if ($step['done'])
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        @endif
                    </span>
                    <div class="flex-1 min-w-0">
                        <p @class(['text-sm font-semibold', 'line-through text-(--color-text-muted)' => $step['done']])>{{ $step['title'] }}</p>
                        @unless ($step['done'])<p class="text-xs text-(--color-text-muted)">{{ $step['text'] }}</p>@endunless
                    </div>
                    @unless ($step['done'])
                        <a href="{{ $step['url'] }}" wire:navigate class="flex-none inline-flex items-center h-9 px-3.5 rounded-lg bg-(--color-primary-tint) text-sm font-semibold text-(--color-primary) hover:bg-(--color-primary) hover:text-white">Me guider</a>
                    @endunless
                </li>
            @endforeach
        </ol>
        <p class="mt-3 text-xs text-(--color-text-muted)">Une question ? Notre équipe vous aide par téléphone ou WhatsApp, depuis la page <a href="{{ route('support') }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Support</a>.</p>
    </section>
@endif
