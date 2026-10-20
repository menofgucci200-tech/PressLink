<?php

namespace App\Livewire\Pressing;

use App\Enums\ActivityAction;
use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Rules\IvorianPhone;
use App\Support\ImageOptimizer;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Profil du pressing : ce que voient les clients (logo, nom, téléphone,
 * adresse, description). La configuration (horaires, conditions,
 * magasinage…) est dans Pressing\Settings.
 */
class Profile extends Component
{
    use ShowsToasts, WithFileUploads;

    public Pressing $pressing;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $description = '';

    public $logo = null;

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::SettingsManage, $pressing), 403);

        $this->pressing = $pressing;
        $this->name = $pressing->name;
        $this->phone = $pressing->phone;
        $this->email = $pressing->email ?? '';
        $this->address = $pressing->address ?? '';
        $this->city = $pressing->city ?? '';
        $this->description = $pressing->description ?? '';
    }

    public function save(): void
    {
        $this->phone = PhoneNumber::normalize($this->phone);

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', new IvorianPhone(mobileOnly: false)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width='.ImageOptimizer::MAX_DIMENSION.',max_height='.ImageOptimizer::MAX_DIMENSION],
        ], [
            'logo.dimensions' => 'Ce logo est trop grand ('.ImageOptimizer::MAX_DIMENSION.' × '.ImageOptimizer::MAX_DIMENSION.' pixels maximum).',
        ]);

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'description' => $this->description ?: null,
        ];

        if ($this->logo) {
            if ($this->pressing->logo_path) {
                Storage::disk('public')->delete($this->pressing->logo_path);
            }

            $data['logo_path'] = ImageOptimizer::store($this->logo, 'pressings');
        }

        $this->pressing->update($data);

        $labels = ['name' => 'nom', 'phone' => 'téléphone', 'email' => 'e-mail', 'address' => 'adresse', 'city' => 'ville',
            'description' => 'description', 'logo_path' => 'logo'];
        $changed = array_intersect_key($labels, $this->pressing->getChanges());
        if ($changed !== []) {
            ActivityLog::record($this->pressing->id, ActivityAction::SettingsChanged, 'Profil modifié : '.implode(', ', $changed),
                null, ['fields' => array_keys($changed)]);
        }

        $this->logo = null;
        $this->toast('Profil du pressing enregistré.');
    }

    public function removeLogo(): void
    {
        if ($this->pressing->logo_path) {
            Storage::disk('public')->delete($this->pressing->logo_path);
            $this->pressing->update(['logo_path' => null]);
            ActivityLog::record($this->pressing->id, ActivityAction::SettingsChanged, 'Logo retiré');
            $this->toast('Logo retiré.');
        }
    }

    #[Layout('layouts.dashboard', ['active' => 'profile', 'title' => 'Profil du pressing'])]
    public function render()
    {
        return view('livewire.pressing.profile');
    }
}
