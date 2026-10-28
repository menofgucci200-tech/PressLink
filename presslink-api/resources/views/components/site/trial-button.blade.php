@props(['tone' => 'primary'])
{{-- « Essayer gratuitement » : la page d'inscription du pressing (2 minutes, sans paiement). --}}
@php
    $days = \App\Support\PlanCatalog::trialDays();
    // Pendant le lancement : la vraie fin de l'essai (fin du lancement + la semaine pour choisir).
    $label = \App\Support\LaunchOffer::isActive()
        ? "Essayer gratuitement jusqu'au ".\App\Models\Subscription::trialEndsAtForNewPressing()->translatedFormat('j F')
        : "Essayer gratuitement {$days} jours";
    $classes = match ($tone) {
        'light' => 'bg-white text-slate-900 hover:bg-slate-100',
        default => 'bg-(--color-primary) text-white hover:bg-(--color-primary-600)',
    };
@endphp
<a href="{{ route('signup') }}"
   {{ $attributes->merge(['class' => "inline-flex flex-col items-center justify-center min-h-12 px-6 py-1.5 rounded-xl font-semibold leading-tight {$classes}"]) }}>
    <span>{{ $label }}</span>
    <span class="text-[11px] font-medium opacity-80">en 2 minutes · sans engagement</span>
</a>
