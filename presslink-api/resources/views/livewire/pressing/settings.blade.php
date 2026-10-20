@php
    $dayLabels = [
        'lundi' => 'Lundi',
        'mardi' => 'Mardi',
        'mercredi' => 'Mercredi',
        'jeudi' => 'Jeudi',
        'vendredi' => 'Vendredi',
        'samedi' => 'Samedi',
        'dimanche' => 'Dimanche',
    ];
@endphp
<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Paramètres</h1>
        <p class="text-sm text-(--color-text-muted)">Horaires, affichage des prix, conditions et règlement, droit de magasinage.</p>
    </div>

    <form novalidate wire:submit="save" class="flex flex-col gap-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Horaires d'ouverture</div>
            <div class="flex flex-col gap-2.5">
                @foreach ($days as $day)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <label class="w-24 text-sm font-medium flex-none">{{ $dayLabels[$day] }}</label>
                        <label class="flex items-center gap-1.5 text-xs text-(--color-text-secondary) flex-none w-24">
                            <input type="checkbox" wire:model="openingHours.{{ $day }}.closed" class="rounded border-(--color-border)">
                            Fermé
                        </label>
                        <input type="time" wire:model="openingHours.{{ $day }}.open" @disabled($openingHours[$day]['closed'])
                               class="h-9 px-2.5 rounded-lg border border-(--color-border) text-sm disabled:opacity-40">
                        <span class="text-(--color-text-muted)">—</span>
                        <input type="time" wire:model="openingHours.{{ $day }}.close" @disabled($openingHours[$day]['closed'])
                               class="h-9 px-2.5 rounded-lg border border-(--color-border) text-sm disabled:opacity-40">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Grille tarifaire</div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" wire:model="showPricingToCustomers" class="mt-0.5 rounded border-(--color-border)">
                <span class="text-sm">
                    Afficher ma grille tarifaire à mes clients
                    <span class="block text-xs text-(--color-text-muted) mt-0.5">Vos clients pourront consulter les prix de vos services depuis l'application mobile, sur la fiche de votre pressing.</span>
                </span>
            </label>
        </div>

        @include('livewire.pressing._terms-and-storage')

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
