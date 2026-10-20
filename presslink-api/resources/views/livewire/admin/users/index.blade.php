@php
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
    $isCustomers = $type === 'customers';
@endphp

<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Liste des utilisateurs</h1>
        <p class="text-sm text-(--color-text-muted)">
            Tous les comptes de PressLink. Une réinitialisation remplace le mot de passe par
            <span class="font-mono font-semibold text-(--color-text-secondary)">{{ $defaultPassword }}</span> :
            après la réinitialisation, l'utilisateur doit changer son mot de passe pour sécuriser son compte.
        </p>
    </div>

    <div class="flex flex-wrap gap-2 mb-5" role="tablist">
        @foreach (\App\Livewire\Admin\Users\Index::TYPES as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}" wire:click="$set('type', '{{ $key }}')"
                    class="inline-flex items-center gap-2 h-9 px-3.5 rounded-full border text-sm font-medium transition-colors {{ $type === $key ? 'border-(--color-primary) bg-(--color-primary-tint) text-(--color-primary)' : 'border-(--color-border) bg-(--color-surface) hover:border-(--color-text-muted)' }}">
                {{ $label }} <span class="tabular-nums font-bold">{{ number_format($counts[$key], 0, ',', ' ') }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="relative w-full sm:w-auto sm:flex-1 sm:max-w-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ $isCustomers ? 'Nom, téléphone ou email…' : 'Nom, identifiant, email ou téléphone…' }}"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
        </div>
        <label class="inline-flex items-center gap-2 text-sm cursor-pointer select-none">
            <input type="checkbox" wire:model.live="mustChangeOnly" class="w-4 h-4 accent-(--color-primary)">
            Mot de passe à changer
            <span class="text-xs text-(--color-text-muted) tabular-nums">({{ $mustChangeCount }})</span>
        </label>
        <span wire:loading.delay class="text-xs text-(--color-text-muted)">Chargement…</span>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($accounts->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun utilisateur trouvé.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[820px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="{{ $th }}">Utilisateur</th>
                        <th class="{{ $th }}">{{ $isCustomers ? 'Téléphone' : 'Identifiant de connexion' }}</th>
                        <th class="{{ $th }}">{{ $type === 'admins' ? 'Accès' : 'Pressings' }}</th>
                        <th class="{{ $th }}">Mot de passe</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr wire:key="{{ $type }}-{{ $account->id }}"
                            @if ($isCustomers) data-href="{{ route('admin.clients.index', ['client' => $account->id]) }}" tabindex="0" role="link" @endif
                            class="border-t border-(--color-border) {{ $isCustomers ? '' : 'hover:bg-(--color-bg)' }}">
                            <td class="px-5 py-3.5">
                                @if ($isCustomers)
                                    <a href="{{ route('admin.clients.index', ['client' => $account->id]) }}" wire:navigate class="font-medium hover:text-(--color-primary)">{{ $account->fullName() ?: 'Sans nom' }}</a>
                                    @if ($account->is_active === false)
                                        <span class="ml-1.5 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)">Désactivé</span>
                                    @endif
                                    @if ($account->email)
                                        <div class="text-xs text-(--color-text-muted)">{{ $account->email }}</div>
                                    @endif
                                @else
                                    <div class="flex items-center gap-3">
                                        <x-user-avatar :user="$account" class="w-8 h-8 text-[11px] rounded-full" />
                                        <div class="min-w-0">
                                            <span class="font-medium">{{ $account->name }}</span>
                                            @if ($account->is(auth()->user()))
                                                <span class="ml-1.5 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-primary-tint) text-(--color-primary)">Vous</span>
                                            @endif
                                            @if ($account->email)
                                                <div class="text-xs text-(--color-text-muted)">{{ $account->email }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary) tabular-nums">
                                @if ($isCustomers)
                                    {{ $account->phone }}
                                @else
                                    <span class="font-mono">{{ $account->login ?? $account->email ?? $account->phone }}</span>
                                    @if ($account->login && $account->phone)
                                        <div class="text-xs text-(--color-text-muted)">{{ $account->phone }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">
                                @if ($type === 'admins')
                                    Console Super Admin
                                @elseif ($account->pressings->isEmpty())
                                    <span class="text-(--color-text-muted)">Aucun</span>
                                @else
                                    @foreach ($account->pressings->take(2) as $p)
                                        <div>
                                            {{ $p->name }}
                                            @if (! $isCustomers)
                                                <span class="text-xs text-(--color-text-muted)">
                                                    · {{ $p->pivot->role instanceof \App\Enums\PressingRole ? $p->pivot->role->label() : ucfirst((string) $p->pivot->role) }}{{ $p->pivot->is_active ? '' : ' (retiré)' }}
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($account->pressings->count() > 2)
                                        <div class="text-xs text-(--color-text-muted)">+{{ $account->pressings->count() - 2 }} autre(s)</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($account->must_change_password)
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-(--color-warning-tint) text-(--color-warning-text)">À changer</span>
                                @else
                                    <span class="text-xs text-(--color-text-muted)">Personnalisé</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @if (! $isCustomers && $account->is(auth()->user()))
                                    <a href="{{ route('account.settings') }}" wire:navigate class="text-xs font-medium text-(--color-primary) hover:underline">Mon compte</a>
                                @else
                                    @php $name = $isCustomers ? ($account->fullName() ?: $account->phone) : $account->name; @endphp
                                    <button type="button"
                                            wire:click="{{ $isCustomers ? 'resetCustomerPassword' : 'resetUserPassword' }}({{ $account->id }})"
                                            wire:loading.attr="disabled" wire:target="{{ $isCustomers ? 'resetCustomerPassword' : 'resetUserPassword' }}({{ $account->id }})"
                                            data-confirm-title="Réinitialiser le mot de passe" data-confirm-label="Réinitialiser" data-confirm-tone="warning"
                                            wire:confirm="Le mot de passe de {{ $name }} sera réinitialisé à {{ $defaultPassword }} et ses sessions ouvertes seront fermées. Après la réinitialisation, il doit changer son mot de passe pour sécuriser son compte."
                                            class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-(--color-border) text-xs font-semibold hover:border-(--color-primary) hover:text-(--color-primary) disabled:opacity-60">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path></svg>
                                        Réinitialiser le mot de passe
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="mt-5">{{ $accounts->links() }}</div>
</div>
