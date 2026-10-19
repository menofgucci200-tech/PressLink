{{--
    Champ de numéro ivoirien : « +225 » fixe, puis 10 chiffres au plus,
    groupés par deux pendant la saisie (« 07 08 12 44 00 »). La propriété
    Livewire liée reçoit le format stocké « +2250708124400 » ; le serveur
    vérifie ensuite qu'il y a bien 10 chiffres (App\Rules\IvorianPhone).

    <x-phone-input wire:model="phone" id="client-phone" />
--}}
@props(['placeholder' => '07 08 12 44 00'])

<div x-data="{
        value: @entangle($attributes->wire('model')),
        digits: '',
        clean(text) {
            let d = String(text ?? '').replace(/\D/g, '');
            if (d.startsWith('00225')) d = d.slice(5);
            else if (d.startsWith('225') && d.length > 10) d = d.slice(3);
            return d.slice(0, 10);
        },
        fromStored(v) {
            v = String(v ?? '');
            return this.clean(v.startsWith('+225') ? v.slice(4) : v);
        },
        stored() { return this.digits ? '+225' + this.digits : ''; },
        pairs(d) { return d.replace(/(\d{2})(?=\d)/g, '$1 '); },
        onInput(event) {
            this.digits = this.clean(event.target.value);
            event.target.value = this.pairs(this.digits);
            this.value = this.stored();
        },
     }"
     x-init="digits = fromStored(value); $watch('value', v => { if (v !== stored()) digits = fromStored(v) })"
     class="flex items-stretch h-10 rounded-lg border border-(--color-border) bg-(--color-surface) overflow-hidden focus-within:border-(--color-primary) focus-within:ring-2 focus-within:ring-(--color-primary)/15 transition-colors">
    <span class="flex items-center gap-1.5 px-3 border-r border-(--color-border) bg-(--color-bg) text-sm font-semibold text-(--color-text-secondary) select-none" aria-hidden="true">
        <span class="text-base leading-none">🇨🇮</span>+225
    </span>
    <input type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="14"
           placeholder="{{ $placeholder }}" aria-label="Numéro de téléphone (10 chiffres)"
           :value="pairs(digits)" @input="onInput($event)"
           {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'flex-1 min-w-0 px-3 text-sm tabular-nums tracking-wide bg-transparent focus:outline-none']) }}>
    <span x-show="digits.length === 10" x-cloak class="flex items-center pr-3 text-(--color-success-text)" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
    </span>
</div>
