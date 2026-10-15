@php $th = 'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) bg-(--color-bg)'; @endphp
<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Équipe du groupe</h1>
            <p class="text-sm text-(--color-text-muted)">Affectez chaque membre aux pressings où il travaille. Un administrateur gère le pressing ; un employé enregistre et suit les commandes.</p>
        </div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-sm" style="min-width: {{ 260 + $pressings->count() * 170 }}px">
            <thead><tr class="text-left">
                <th class="{{ $th }}">Membre</th>
                @foreach ($pressings as $p)
                    <th class="{{ $th }}">{{ $p->name }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach ($members as $member)
                    @php $isMe = $member->is(auth()->user()); @endphp
                    <tr wire:key="tm-{{ $member->id }}" class="border-t border-(--color-border)">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-user-avatar :user="$member" class="w-8 h-8 text-[11px] rounded-full" />
                                <div>
                                    <div class="font-medium">{{ $member->name }} @if ($isMe)<span class="ml-1 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-(--color-primary-tint) text-(--color-primary)">Vous</span>@endif</div>
                                    <div class="text-xs text-(--color-text-muted) font-mono">{{ $member->login ?? $member->email }}</div>
                                </div>
                            </div>
                        </td>
                        @foreach ($pressings as $p)
                            @php
                                $pivot = $member->pressings->firstWhere('id', $p->id)?->pivot;
                                $value = $pivot && $pivot->is_active ? ($pivot->role instanceof \App\Enums\PressingRole ? $pivot->role->value : $pivot->role) : \App\Livewire\Group\Team::NONE;
                            @endphp
                            <td class="px-4 py-3">
                                <select wire:key="sel-{{ $member->id }}-{{ $p->id }}-{{ $value }}" @disabled($isMe)
                                        wire:change="assign({{ $member->id }}, {{ $p->id }}, $event.target.value)"
                                        aria-label="Rôle de {{ $member->name }} à {{ $p->name }}"
                                        class="h-9 px-2.5 rounded-lg border text-sm disabled:opacity-60 {{ $value === \App\Livewire\Group\Team::NONE ? 'border-dashed border-(--color-border) text-(--color-text-muted) bg-transparent' : ($value === 'admin' ? 'border-(--color-primary) bg-(--color-primary-tint) text-(--color-primary) font-semibold' : 'border-(--color-border) bg-(--color-surface)') }}">
                                    <option value="{{ \App\Livewire\Group\Team::NONE }}" @selected($value === \App\Livewire\Group\Team::NONE)>— Pas d'accès</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($value === $role->value)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
    <p class="mt-3 text-xs text-(--color-text-muted)">Pour créer un nouveau membre (identifiant, mot de passe), passez par l'Équipe d'un pressing, puis affectez-le ici aux autres sites. Les limites de chaque formule s'appliquent.</p>
</div>
