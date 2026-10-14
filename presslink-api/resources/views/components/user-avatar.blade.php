@props(['user'])
{{-- Photo de profil d'un membre du staff ou d'un super admin, ou ses
     initiales sur fond bleu PressLink tant qu'il n'en a pas envoyé. --}}
@if ($user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="" {{ $attributes->merge(['class' => 'flex-none rounded-lg object-cover bg-(--color-bg)']) }}>
@else
    <span {{ $attributes->merge(['class' => 'flex-none rounded-lg inline-flex items-center justify-center font-bold text-white bg-[#1e3a8a] select-none']) }} aria-hidden="true">{{ $user->initials ?: '?' }}</span>
@endif
