{{--
    Boîte de confirmation et notifications de l'interface (voir
    resources/js/feedback.js). Les notifications arrivent par l'événement
    navigateur « toast » — `$this->dispatch('toast', ...)` côté Livewire —
    ou par la session (`session()->flash('toast', [...])`) après une
    redirection.
--}}
@php
    $initialToasts = collect([session('toast')])->filter()->values();
@endphp

<div data-press-feedback
     x-data="{
        dialog: null,
        toasts: [],
        nextId: 1,
        open(detail) {
            this.dialog = {
                title: detail.title || 'Confirmer l’action',
                message: detail.message || '',
                confirmLabel: detail.confirmLabel || 'Confirmer',
                cancelLabel: detail.cancelLabel || 'Annuler',
                tone: detail.tone || 'primary',
                resolve: detail.resolve,
            };
            this.$nextTick(() => this.$refs.confirmButton?.focus());
        },
        close(result) {
            if (! this.dialog) return;
            const resolve = this.dialog.resolve;
            this.dialog = null;
            resolve?.(result);
        },
        push(detail) {
            const id = this.nextId++;
            const type = ['success', 'error', 'warning', 'info'].includes(detail.type) ? detail.type : 'success';
            this.toasts.push({ id, type, message: detail.message });
            setTimeout(() => this.dismiss(id), type === 'error' ? 7000 : 4500);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
     }"
     x-init="@js($initialToasts).forEach((t) => push(t))"
     @press:confirm.window="open($event.detail)"
     @toast.window="push(Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
     @keydown.escape.window="close(false)">

    {{-- ===== Boîte de confirmation ===== --}}
    <template x-teleport="body">
        <div x-show="dialog" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4"
             role="alertdialog" aria-modal="true" :aria-label="dialog?.title">
            <div x-show="dialog" x-transition.opacity.duration.150ms class="absolute inset-0 bg-slate-900/50" @click="close(false)"></div>

            <div x-show="dialog"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 sm:scale-95"
                 class="relative w-full max-w-md rounded-2xl bg-(--color-surface) shadow-2xl p-6"
                 @keydown.enter.prevent="close(true)">
                <div class="flex items-start gap-4">
                    <div class="flex-none w-11 h-11 rounded-full flex items-center justify-center"
                         :class="{
                            'bg-(--color-error-tint) text-(--color-error)': dialog?.tone === 'danger',
                            'bg-(--color-warning-tint) text-(--color-warning-text)': dialog?.tone === 'warning',
                            'bg-(--color-primary-tint) text-(--color-primary)': ! ['danger', 'warning'].includes(dialog?.tone),
                         }">
                        <svg x-show="dialog?.tone === 'danger'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>
                        <svg x-show="dialog?.tone === 'warning'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                        <svg x-show="! ['danger', 'warning'].includes(dialog?.tone)" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-lg font-bold leading-snug" x-text="dialog?.title"></h2>
                        <p class="mt-1.5 text-sm text-(--color-text-secondary) whitespace-pre-line" x-text="dialog?.message"></p>
                    </div>
                </div>
                <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" @click="close(false)"
                            class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold text-(--color-text-secondary) hover:border-(--color-text-muted)"
                            x-text="dialog?.cancelLabel"></button>
                    <button type="button" x-ref="confirmButton" @click="close(true)"
                            class="h-10 px-4 rounded-lg text-sm font-semibold text-white focus:outline-none focus:ring-4"
                            :class="{
                                'bg-(--color-error) hover:brightness-95 focus:ring-(--color-error-tint)': dialog?.tone === 'danger',
                                'bg-(--color-warning) hover:brightness-95 focus:ring-(--color-warning-tint)': dialog?.tone === 'warning',
                                'bg-(--color-primary) hover:bg-(--color-primary-600) focus:ring-(--color-primary-tint)': ! ['danger', 'warning'].includes(dialog?.tone),
                            }"
                            x-text="dialog?.confirmLabel"></button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== Notifications ===== --}}
    <template x-teleport="body">
        <div class="fixed z-[110] inset-x-0 top-3 sm:top-auto sm:bottom-5 sm:right-5 sm:left-auto flex flex-col items-center sm:items-end gap-2.5 px-4 sm:px-0 pointer-events-none" aria-live="polite">
            <template x-for="t in toasts" :key="t.id">
                <div x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                     class="pointer-events-auto w-full sm:w-[380px] flex items-start gap-3 rounded-xl bg-(--color-surface) border border-(--color-border) shadow-lg px-4 py-3.5"
                     :role="t.type === 'error' ? 'alert' : 'status'">
                    <span class="flex-none w-7 h-7 rounded-full flex items-center justify-center"
                          :class="{
                            'bg-(--color-success-tint) text-(--color-success-text)': t.type === 'success',
                            'bg-(--color-error-tint) text-(--color-error)': t.type === 'error',
                            'bg-(--color-warning-tint) text-(--color-warning-text)': t.type === 'warning',
                            'bg-(--color-info-tint) text-(--color-info)': t.type === 'info',
                          }">
                        <svg x-show="t.type === 'success'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                        <svg x-show="t.type === 'error'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                        <svg x-show="t.type === 'warning' || t.type === 'info'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><path d="M12 8v5"></path><path d="M12 16.5h.01"></path></svg>
                    </span>
                    <p class="flex-1 min-w-0 text-sm font-medium leading-snug pt-1" x-text="t.message"></p>
                    <button type="button" @click="dismiss(t.id)" aria-label="Fermer" class="flex-none -mr-1 w-7 h-7 rounded-md flex items-center justify-center text-(--color-text-muted) hover:text-(--color-text-primary)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                    </button>
                </div>
            </template>
        </div>
    </template>
</div>
