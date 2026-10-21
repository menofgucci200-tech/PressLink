{{-- Bouton « Exporter » d'un pressing sans l'option Exports : mène à l'option (propriétaire seulement). --}}
@php
    $user = auth()->user();
    $show = $user->featureLockedFor(\App\Enums\Permission::ReportsExport) !== null && $user->may(\App\Enums\Permission::SubscriptionManage);
@endphp
@if ($show)
    <a href="{{ route('subscription.show', ['option' => \App\Enums\Feature::Exports->value]) }}#options" wire:navigate
       title="Les exports Excel et PDF ne sont pas inclus dans votre formule"
       class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold text-(--color-text-muted) hover:border-(--color-primary) hover:text-(--color-primary)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        Exporter
        <x-lock-icon />
    </a>
@endif
