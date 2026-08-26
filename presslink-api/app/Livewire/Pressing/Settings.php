<?php

namespace App\Livewire\Pressing;

use App\Models\Pressing;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Paramètres du pressing — Phase 2 du dashboard (nom, logo, adresse,
 * téléphone, horaires d'ouverture).
 */
class Settings extends Component
{
    use WithFileUploads;

    private const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public Pressing $pressing;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $description = '';

    /** @var array<string, array{closed: bool, open: string, close: string}> */
    public array $openingHours = [];

    public $logo = null;

    public bool $saved = false;

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->isAdminOf($pressing), 403);

        $this->pressing = $pressing;
        $this->name = $pressing->name;
        $this->phone = $pressing->phone;
        $this->email = $pressing->email ?? '';
        $this->address = $pressing->address ?? '';
        $this->city = $pressing->city ?? '';
        $this->description = $pressing->description ?? '';
        $this->openingHours = $this->normalizeOpeningHours($pressing->opening_hours);
    }

    public function save(): void
    {
        $this->saved = false;

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'openingHours.*.closed' => ['boolean'],
            'openingHours.*.open' => ['nullable', 'date_format:H:i'],
            'openingHours.*.close' => ['nullable', 'date_format:H:i'],
        ]);

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'description' => $this->description ?: null,
            'opening_hours' => $this->openingHours,
        ];

        if ($this->logo) {
            if ($this->pressing->logo_path) {
                Storage::disk('public')->delete($this->pressing->logo_path);
            }

            $data['logo_path'] = $this->logo->store('pressings', 'public');
        }

        $this->pressing->update($data);
        $this->logo = null;
        $this->saved = true;
    }

    public function removeLogo(): void
    {
        if ($this->pressing->logo_path) {
            Storage::disk('public')->delete($this->pressing->logo_path);
            $this->pressing->update(['logo_path' => null]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $hours
     * @return array<string, array{closed: bool, open: string, close: string}>
     */
    private function normalizeOpeningHours(?array $hours): array
    {
        $normalized = [];

        foreach (self::DAYS as $day) {
            $normalized[$day] = [
                'closed' => $hours[$day]['closed'] ?? false,
                'open' => $hours[$day]['open'] ?? '08:00',
                'close' => $hours[$day]['close'] ?? '18:00',
            ];
        }

        return $normalized;
    }

    #[Layout('layouts.dashboard', ['active' => 'settings', 'title' => 'Paramètres'])]
    public function render()
    {
        return view('livewire.pressing.settings', [
            'days' => self::DAYS,
        ]);
    }
}
