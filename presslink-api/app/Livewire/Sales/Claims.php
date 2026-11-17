<?php

namespace App\Livewire\Sales;

use App\Livewire\Concerns\ActsAsSalesAgent;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Pressing;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Ce pressing vient de moi » : le commercial signale un pressing qu'il a
 * présenté mais qui s'est inscrit seul, sans son code. L'équipe PressLink
 * vérifie et le rattache depuis la console.
 */
class Claims extends Component
{
    use ActsAsSalesAgent, ShowsToasts;

    public string $pressingName = '';

    public string $phone = '';

    public string $note = '';

    public function mount(): void
    {
        $this->agent();
    }

    public function submit(): void
    {
        $agent = $this->agent();
        $this->phone = $this->phone === '' ? '' : PhoneNumber::normalize($this->phone);

        $this->validate([
            'pressingName' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', new IvorianPhone(mobileOnly: false)],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'pressingName.required' => 'Indiquez le nom du pressing.',
            'phone.required' => 'Indiquez le numéro du pressing ou de son gérant.',
        ]);

        $pressing = self::pressingWithPhone($this->phone);

        if ($pressing?->sales_agent_id === $agent->id) {
            throw ValidationException::withMessages(['phone' => 'Ce pressing vous est déjà rattaché : il est dans « Mes pressings ».']);
        }

        if ($agent->claims()->pending()->where('phone', $this->phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Vous avez déjà signalé ce numéro : l\'équipe PressLink le vérifie.']);
        }

        $agent->claims()->create([
            'pressing_id' => $pressing?->id,
            'pressing_name' => trim($this->pressingName),
            'phone' => $this->phone,
            'note' => trim($this->note) ?: null,
        ]);

        $this->reset(['pressingName', 'phone', 'note']);
        $this->toast('Signalement envoyé : l\'équipe PressLink vérifie et vous rattache le pressing.');
    }

    /** Pressing inscrit (hors tests) dont c'est le numéro, ou celui de son gérant. */
    public static function pressingWithPhone(string $phone): ?Pressing
    {
        return Pressing::query()->withoutSandboxes()->withoutDemo()
            ->where(fn ($query) => $query->where('phone', $phone)
                ->orWhereHas('admins', fn ($admins) => $admins->where('users.phone', $phone)))
            ->oldest()
            ->first();
    }

    #[Layout('layouts.sales', ['active' => 'claims', 'title' => 'Signaler un pressing — PressLink'])]
    public function render(): View
    {
        return view('livewire.sales.claims', [
            'claims' => $this->agent()->claims()->latest()->limit(30)->get(),
        ]);
    }
}
