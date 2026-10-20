@props(['user', 'role', 'context' => null, 'active' => false])
{{-- Menu de profil de l'en-tête des dashboards (pressing et Super Admin) :
     photo / initiales, accès à « Mon profil », au mot de passe et à la
     déconnexion. --}}
@php
    $item = 'flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium text-(--color-text-primary) hover:bg-(--color-bg)';
@endphp
<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" title="Mon profil"
            class="flex items-center gap-3 rounded-xl p-1 sm:pl-3 hover:bg-(--color-bg) {{ $active ? 'bg-(--color-bg)' : '' }}"
            :class="open && 'bg-(--color-bg)'">
        <span class="hidden sm:block text-right min-w-0">
            <span class="block text-sm font-semibold leading-tight truncate max-w-56">{{ $user->name }}</span>
            <span class="block text-xs text-(--color-text-muted) truncate max-w-56">{{ $role }}{{ $context ? ' · '.$context : '' }}</span>
        </span>
        <x-user-avatar :user="$user" class="w-9 h-9 text-xs {{ $active ? 'ring-2 ring-offset-1 ring-offset-(--color-surface) ring-(--color-primary)' : '' }}" />
        <svg class="hidden sm:block w-4 h-4 flex-none text-(--color-text-muted) transition-transform duration-150" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right.duration.150ms role="menu"
         @click="if ($event.target.closest('a')) open = false"
         class="absolute right-0 mt-2 w-72 max-w-[calc(100vw-2rem)] bg-(--color-surface) border border-(--color-border) rounded-xl shadow-lg z-30 overflow-hidden">
        <div class="p-4 flex items-center gap-3 border-b border-(--color-border)">
            <x-user-avatar :user="$user" class="w-11 h-11 text-sm rounded-xl" />
            <div class="min-w-0">
                <div class="text-sm font-semibold truncate">{{ $user->name }}</div>
                <div class="text-xs text-(--color-text-muted) truncate">{{ $user->email ?? $user->login ?? $user->phone }}</div>
                <div class="text-xs text-(--color-text-secondary) truncate">{{ $role }}{{ $context ? ' · '.$context : '' }}</div>
            </div>
        </div>
        <div class="p-1.5">
            <a href="{{ route('account.settings') }}" wire:navigate role="menuitem" class="{{ $item }}">
                <svg class="w-4 h-4 flex-none text-(--color-text-muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Mon profil
            </a>
            <a href="{{ route('account.settings') }}#securite" role="menuitem" class="{{ $item }}">
                <svg class="w-4 h-4 flex-none text-(--color-text-muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Mot de passe et connexion
            </a>
            {{ $slot }}
        </div>
        <div class="p-1.5 border-t border-(--color-border)">
            <form novalidate method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" role="menuitem" class="{{ $item }} text-(--color-error)! hover:bg-(--color-error-tint)!">
                    <svg class="w-4 h-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Se déconnecter
                </button>
            </form>
        </div>
    </div>
</div>
