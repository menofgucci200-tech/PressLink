<div class="max-w-4xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Site vitrine</h1>
        <p class="text-sm text-(--color-text-muted)">Témoignages de gérants affichés sur les pages « Pour les pressings » et « Nos offres ». Sans témoignage, le bloc n'apparaît pas : jamais de faux avis.</p>
    </div>

    <form novalidate wire:submit="add" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="t-name" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prénom et nom du gérant</label>
            <input id="t-name" type="text" wire:model="form.name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            @error('form.name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="t-pressing" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Pressing</label>
            <input id="t-pressing" type="text" wire:model="form.pressing" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            @error('form.pressing') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="t-commune" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Commune (facultatif)</label>
            <input id="t-commune" type="text" wire:model="form.commune" placeholder="ex. Cocody" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            @error('form.commune') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-3">
            <label for="t-quote" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Témoignage, tel que le gérant l'a écrit ou validé</label>
            <textarea id="t-quote" wire:model="form.quote" rows="3" maxlength="400" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
            @error('form.quote') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="flex items-start gap-2.5 text-sm cursor-pointer">
                <input type="checkbox" wire:model="form.consent" class="mt-0.5 w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                <span>Le gérant a donné son accord écrit pour publier ce témoignage, son nom et celui de son pressing.</span>
            </label>
            @error('form.consent') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-end justify-end">
            <button type="submit" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Publier</button>
        </div>
    </form>

    @if ($testimonials === [])
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-8 text-center text-sm text-(--color-text-muted)">Aucun témoignage pour l'instant. Demandez-en un à vos premiers pressings satisfaits.</div>
    @else
        <ul class="space-y-3">
            @foreach ($testimonials as $t)
                <li class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5" wire:key="t-{{ $t['id'] ?? $loop->index }}">
                    <blockquote class="text-sm text-(--color-text-secondary)">« {{ $t['quote'] ?? '' }} »</blockquote>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span><span class="font-semibold">{{ $t['name'] ?? '' }}</span> <span class="text-(--color-text-muted)">· {{ $t['pressing'] ?? '' }}@if (! empty($t['commune'])), {{ $t['commune'] }}@endif</span></span>
                        <span class="flex items-center gap-4">
                            <span class="text-xs font-medium {{ ($t['published'] ?? true) ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">{{ ($t['published'] ?? true) ? 'En ligne' : 'Masqué' }}</span>
                            <button type="button" wire:click="togglePublished('{{ $t['id'] ?? '' }}')" class="text-xs font-semibold text-(--color-primary) hover:underline">{{ ($t['published'] ?? true) ? 'Masquer' : 'Remettre en ligne' }}</button>
                            <button type="button" wire:click="remove('{{ $t['id'] ?? '' }}')" wire:confirm="Supprimer ce témoignage ?" class="text-xs font-semibold text-(--color-text-muted) hover:text-(--color-error)">Supprimer</button>
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
