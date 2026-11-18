@php
    $levels = [
        'ok' => ['label' => 'OK', 'class' => 'bg-(--color-success-tint) text-(--color-success-text)'],
        'warning' => ['label' => 'À surveiller', 'class' => 'bg-(--color-warning-tint) text-(--color-warning-text)'],
        'error' => ['label' => 'Problème', 'class' => 'bg-(--color-error-tint) text-(--color-error)'],
    ];
    $all = collect($sections)->flatMap(fn ($section) => $section['checks']);
    $errors = $all->where('level', 'error')->count();
    $warnings = $all->where('level', 'warning')->count();
@endphp
<div class="max-w-4xl">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Santé de la plateforme</h1>
            <p class="text-sm text-(--color-text-muted)">Serveur, base de données, tâches de fond et espace disque, mesurés à l'ouverture de cette page.</p>
        </div>
        <button type="button" wire:click="$refresh" class="h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-medium hover:bg-(--color-bg)">Mesurer à nouveau</button>
    </div>

    <div class="mb-6 rounded-xl px-4 py-3 text-sm font-medium {{ $errors > 0 ? $levels['error']['class'] : ($warnings > 0 ? $levels['warning']['class'] : $levels['ok']['class']) }}" data-testid="health-summary">
        @if ($errors > 0)
            {{ $errors }} problème{{ $errors > 1 ? 's' : '' }} à corriger{{ $warnings > 0 ? ', '.$warnings.' point'.($warnings > 1 ? 's' : '').' à surveiller' : '' }}.
        @elseif ($warnings > 0)
            Tout fonctionne ; {{ $warnings }} point{{ $warnings > 1 ? 's' : '' }} à surveiller.
        @else
            Tout est en ordre.
        @endif
    </div>

    <div class="flex flex-col gap-5">
        @foreach ($sections as $section)
            <section class="bg-(--color-surface) border border-(--color-border) rounded-xl">
                <h2 class="px-5 pt-4 pb-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">{{ $section['title'] }}</h2>
                <ul class="divide-y divide-(--color-border)">
                    @foreach ($section['checks'] as $check)
                        <li class="px-5 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                <span class="text-sm font-medium">{{ $check['label'] }}</span>
                                <span class="flex items-center gap-2">
                                    <span class="text-sm tabular-nums text-(--color-text-secondary)">{{ $check['value'] }}</span>
                                    <span class="inline-flex items-center h-6 px-2 rounded-full text-[11px] font-semibold {{ $levels[$check['level']]['class'] }}">{{ $levels[$check['level']]['label'] }}</span>
                                </span>
                            </div>
                            @if ($check['hint'])
                                <p class="mt-1 text-xs text-(--color-text-muted)">{{ $check['hint'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</div>
