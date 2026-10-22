@props(['tone' => 'primary'])
{{-- « Essayer gratuitement » : ouvre WhatsApp avec un message prêt ; l'équipe PressLink crée l'espace du pressing. --}}
@php
    $days = \App\Support\PlanCatalog::trialDays();
    $url = 'https://wa.me/'.ltrim((string) config('presslink.support.phone_e164'), '+').'?text='
        .rawurlencode("Bonjour, je souhaite essayer PressLink gratuitement pendant {$days} jours.\nNom du pressing : \nCommune : ");
    $classes = match ($tone) {
        'light' => 'bg-white text-slate-900 hover:bg-slate-100',
        default => 'bg-(--color-primary) text-white hover:bg-(--color-primary-600)',
    };
@endphp
<a href="{{ $url }}" target="_blank" rel="noopener"
   {{ $attributes->merge(['class' => "inline-flex flex-col items-center justify-center min-h-12 px-6 py-1.5 rounded-xl font-semibold leading-tight {$classes}"]) }}>
    <span>Essayer gratuitement {{ $days }} jours</span>
    <span class="text-[11px] font-medium opacity-80">via WhatsApp · sans engagement</span>
</a>
