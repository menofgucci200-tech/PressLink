{{--
    Numéro déjà enregistré (par un autre pressing ou par le client lui-même) :
    le nom connu est gardé, le staff confirme qu'il s'agit bien de son client.
    C'est aussi ce qui rattrape une erreur de saisie du numéro.
--}}
<div class="sm:col-span-3 rounded-xl border border-(--color-warning) bg-(--color-warning-tint) p-4 animate-step" role="alert">
    <div class="flex items-start gap-3">
        <span class="flex-none w-8 h-8 rounded-full bg-(--color-surface) text-(--color-warning-text) flex items-center justify-center" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        </span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-(--color-text-primary)">Ce numéro est déjà enregistré au nom de {{ $name }}.</p>
            <p class="text-xs text-(--color-text-secondary) mt-1">Est-ce bien votre client ? Il sera ajouté à vos clients sous ce nom. Lui seul pourra le modifier, en créant son compte PressLink.</p>
            <div class="flex flex-wrap gap-2 mt-3">
                <button type="button" wire:click="confirmKnownCustomer" wire:loading.attr="disabled" wire:target="confirmKnownCustomer"
                        class="h-9 px-3.5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Oui, c'est mon client</button>
                <button type="button" wire:click="dismissKnownCustomer"
                        class="h-9 px-3.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-medium hover:border-(--color-primary)">Corriger le numéro</button>
            </div>
        </div>
    </div>
</div>
