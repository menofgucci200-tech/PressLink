@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) bg-(--color-bg) lg:sticky lg:top-16 lg:z-[5]';
@endphp
<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Clients du groupe</h1>
        <p class="text-sm text-(--color-text-muted)">{{ $fcfa($totalCustomers) }} client(s) · {{ $fcfa($sharedCustomers) }} fréquentent plusieurs de vos pressings</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="relative w-full sm:w-auto sm:flex-1 sm:max-w-xs">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom ou téléphone…" aria-label="Rechercher"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
        </div>
        <select wire:model.live="pressingId" aria-label="Pressing" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            <option value="">Tous les pressings</option>
            @foreach ($pressings as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="sort" aria-label="Trier par" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            @foreach ($sorts as $key => $label)
                <option value="{{ $key }}">Trier : {{ $label }}</option>
            @endforeach
        </select>
        <label class="inline-flex items-center gap-2 text-sm select-none">
            <input type="checkbox" wire:model.live="multiOnly" class="w-4 h-4 accent-(--color-primary)"> Clients de plusieurs pressings
        </label>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-clip list-loading-target" wire:loading.delay.class="is-loading" wire:target="search, pressingId, sort, multiOnly, gotoPage, nextPage, previousPage">
        @if ($customers->isEmpty())
            <x-empty-state icon="search" title="Aucun client pour ces critères" />
        @else
            <div class="overflow-x-auto lg:overflow-visible"><table class="w-full min-w-[820px] whitespace-nowrap text-sm">
                <thead><tr class="text-left">
                    <th class="{{ $th }}">Client</th><th class="{{ $th }}">Pressings</th><th class="{{ $th }} text-right">Commandes</th>
                    <th class="{{ $th }} text-right">Total dépensé</th><th class="{{ $th }}">Dernière commande</th>
                </tr></thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr wire:key="gcl-{{ $customer->id }}" class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $customer->fullName() }}</div>
                                <div class="text-xs text-(--color-text-muted) tabular-nums">{{ $customer->phone }} <x-copy-button :value="$customer->phone" label="Copier le téléphone" class="copy-btn--reveal" /></div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($customer->pressings as $p)
                                        <a href="{{ route('pressings.switch', ['pressing' => $p, 'vers' => '/clients/'.$customer->id]) }}"
                                           class="text-[11.5px] font-medium px-2 py-0.5 rounded-full bg-(--color-primary-tint) text-(--color-primary) hover:underline">{{ $p->name }}</a>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold">{{ $customer->group_orders_count }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $fcfa((int) $customer->group_spent) }} F</td>
                            <td class="px-5 py-3 text-(--color-text-secondary)">{{ $customer->group_last_order_at ? \Illuminate\Support\Carbon::parse($customer->group_last_order_at)->diffForHumans() : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-5">{{ $customers->links() }}</div>
</div>
