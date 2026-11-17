@php
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
@endphp
<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Commerciaux</h1>
            <p class="text-sm text-(--color-text-muted)">Ils inscrivent des pressings et touchent une commission sur leur abonnement.</p>
        </div>
        <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouveau commercial
        </button>
    </div>

    @if ($createdAgent)
        <div class="mb-6 p-4 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm" data-testid="agent-created">
            <p class="font-semibold mb-1">{{ $createdAgent->user->name }} est créé. Code commercial : <span class="font-mono">{{ $createdAgent->code }}</span></p>
            <p>Il se connecte sur presslink.org/espace/connexion avec son numéro <span class="font-semibold tabular-nums">{{ \App\Support\PhoneNumber::national($createdAgent->user->phone) }}</span> et le mot de passe que vous venez de choisir.</p>
        </div>
    @endif

    @if ($showCreateForm)
        <form novalidate wire:submit="create" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div class="sm:col-span-2">
                <label class="{{ $label }}" for="agent-name">Nom complet</label>
                <input id="agent-name" type="text" wire:model="name" class="{{ $input }}">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}">Téléphone (pour se connecter)</label>
                <x-phone-input wire:model="phone" />
                @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}" for="agent-password">Mot de passe provisoire</label>
                <input id="agent-password" type="text" wire:model="password" autocomplete="off" class="{{ $input }}">
                @error('password') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}" for="agent-rate">Commission (%) <span class="font-normal text-(--color-text-muted)">vide = taux commun ({{ \App\Models\SalesAgent::defaultCommissionRate() }} %)</span></label>
                <input id="agent-rate" type="number" min="0" max="100" wire:model="rate" class="{{ $input }}">
                @error('rate') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 flex items-center gap-2 justify-end">
                <button type="button" wire:click="$set('showCreateForm', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                <button type="submit" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Créer le commercial</button>
            </div>
        </form>
    @endif

    @if ($claims->isNotEmpty())
        <div class="bg-(--color-surface) border border-(--color-warning) rounded-xl overflow-hidden mb-6">
            <div class="px-5 py-4">
                <h2 class="font-display text-base font-bold">Signalements à vérifier</h2>
                <p class="text-sm text-(--color-text-muted)">Un commercial dit avoir apporté ce pressing. Vérifiez, puis rattachez-le.</p>
            </div>
            @foreach ($claims as $claim)
                <div class="flex flex-wrap items-start gap-4 px-5 py-4 border-t border-(--color-border) text-sm" data-claim="{{ $claim->id }}">
                    <div class="flex-1 min-w-56">
                        <div class="font-semibold">{{ $claim->pressing_name }} · <span class="tabular-nums font-normal">{{ \App\Support\PhoneNumber::national($claim->phone) }}</span></div>
                        <div class="text-xs text-(--color-text-muted)">Par {{ $claim->salesAgent->user->name }} ({{ $claim->salesAgent->code }}), le {{ $claim->created_at->format('d/m/Y') }}</div>
                        @if ($claim->note) <div class="mt-1 text-(--color-text-secondary)">« {{ $claim->note }} »</div> @endif
                        @if ($claim->pressing?->salesAgent)
                            <div class="mt-1 text-xs text-(--color-warning-text)">Déjà rattaché à {{ $claim->pressing->salesAgent->user->name }} : le valider le change de commercial.</div>
                        @elseif (! $claim->pressing)
                            <div class="mt-1 text-xs text-(--color-text-muted)">Aucun pressing trouvé avec ce numéro : choisissez-le dans la liste.</div>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-start gap-2">
                        <div>
                            <select wire:model="claimPressing.{{ $claim->id }}" class="h-9 px-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm max-w-64">
                                <option value="">{{ $claim->pressing ? $claim->pressing->name.' (trouvé par le numéro)' : 'Choisir le pressing…' }}</option>
                                @foreach ($pressingOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}{{ $option->city ? ' — '.$option->city : '' }}</option>
                                @endforeach
                            </select>
                            @error('claimPressing.'.$claim->id) <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="approveClaim({{ $claim->id }})" class="h-9 px-3 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Rattacher</button>
                        <button type="button" wire:click="declineClaim({{ $claim->id }})" class="h-9 px-3 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-error) hover:text-(--color-error)">Refuser</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden mb-6">
        @if ($agents->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun commercial pour l'instant.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[760px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="{{ $th }}">Commercial</th>
                        <th class="{{ $th }}">Code</th>
                        <th class="{{ $th }} text-right">Pressings</th>
                        <th class="{{ $th }} text-right">Ce mois</th>
                        <th class="{{ $th }}">Commission</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agents as $agent)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg) {{ $agent->isActive() ? '' : 'opacity-60' }}" data-agent="{{ $agent->id }}">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $agent->user->name }} @unless ($agent->isActive()) <span class="text-xs text-(--color-error)">· suspendu</span> @endunless</div>
                                <div class="text-xs text-(--color-text-muted) tabular-nums">{{ \App\Support\PhoneNumber::national($agent->user->phone) }}</div>
                            </td>
                            <td class="px-5 py-3 font-mono">{{ $agent->code }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                <a href="{{ route('admin.pressings.index', ['commercial' => $agent->id]) }}" wire:navigate class="text-(--color-primary) hover:underline">{{ $agent->pressings_count }}</a>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $agent->pressings_this_month }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-1.5">
                                    <input type="number" min="0" max="100" wire:model="rates.{{ $agent->id }}" placeholder="{{ $agent->commissionRate() }}" class="w-16 h-8 px-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums" aria-label="Commission de {{ $agent->user->name }} (%)">
                                    <span class="text-xs text-(--color-text-muted)">%{{ $agent->commission_rate === null ? ' (commun)' : '' }}</span>
                                    <button type="button" wire:click="saveRate({{ $agent->id }})" class="text-xs font-semibold text-(--color-primary) hover:underline">OK</button>
                                </div>
                                @error('rates.'.$agent->id) <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button type="button" wire:click="toggleSuspension({{ $agent->id }})" class="text-xs font-semibold {{ $agent->isActive() ? 'text-(--color-error)' : 'text-(--color-primary)' }} hover:underline">
                                    {{ $agent->isActive() ? 'Suspendre' : 'Réactiver' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <form novalidate wire:submit="saveDefaultRate" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 max-w-md">
        <h2 class="font-display text-base font-bold mb-1">Taux commun</h2>
        <p class="text-sm text-(--color-text-muted) mb-3">Commission sur l'abonnement des pressings apportés, pour les commerciaux sans taux à eux.</p>
        <div class="flex items-center gap-2">
            <input type="number" min="0" max="100" wire:model="defaultRate" class="w-24 h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums" aria-label="Taux commun (%)">
            <span class="text-sm">%</span>
            <button type="submit" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Enregistrer</button>
        </div>
        @error('defaultRate') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
    </form>
</div>
