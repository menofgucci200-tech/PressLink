{{-- État du règlement d'une commande : payée, acompte (avec le reste) ou à payer. --}}
@props(['order', 'compact' => false])
@php
    $state = $order->paymentStatus();
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    [$fg, $bg, $label] = $order->status === \App\Enums\OrderStatus::Annulee
        ? ['var(--color-text-muted)', 'var(--color-bg)', 'Rien à payer']
        : match ($state) {
        \App\Enums\PaymentStatus::Paid => ['var(--color-success-text)', 'var(--color-success-tint)', 'Payée'],
        \App\Enums\PaymentStatus::Partial => ['var(--color-warning-text)', 'var(--color-warning-tint)', 'Reste '.$fcfa($order->balance_due_fcfa).' F'],
        \App\Enums\PaymentStatus::Unpaid => ['var(--color-text-secondary)', 'var(--color-bg)', $compact ? 'À payer' : 'À payer au retrait'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap']) }}
      style="color:{{ $fg }};background:{{ $bg }}"
      title="{{ $state->label() }} · payé {{ $fcfa($order->amount_paid_fcfa) }} F sur {{ $fcfa($order->total_fcfa) }} F">
    @if ($state === \App\Enums\PaymentStatus::Paid)
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
    @endif
    {{ $label }}
</span>
