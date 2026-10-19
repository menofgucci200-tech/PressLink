{{-- Prix d'une pièce (ou variante) pour un service : enregistré à la sortie de la case, vide = non proposée. --}}
<div class="inline-flex items-center gap-1.5">
    <input type="text" inputmode="numeric" autocomplete="off" value="{{ $value }}" placeholder="—"
           wire:change="setPrice({{ $piece->id }}, {{ $variant ? $variant->id : 'null' }}, {{ $prestation->id }}, $event.target.value)"
           aria-label="Prix de {{ $piece->name }}{{ $variant ? ' ('.$variant->name.')' : '' }} en {{ $prestation->name }}"
           class="w-24 h-9 px-2 text-right tabular-nums rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary) {{ $value === null ? 'bg-(--color-bg)' : 'font-medium' }}">
    <span class="text-xs text-(--color-text-muted)">F</span>
</div>
