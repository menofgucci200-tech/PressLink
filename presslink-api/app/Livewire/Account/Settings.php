<?php

namespace App\Livewire\Account;

use App\Livewire\Concerns\ShowsToasts;
use App\Support\ImageOptimizer;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Mon profil" — tout membre du staff (admin ou employé) et tout super
 * admin y personnalise son propre profil (photo, nom, email, téléphone),
 * son login et son mot de passe, sans passer par quelqu'un d'autre.
 */
class Settings extends Component
{
    use ShowsToasts, WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public $avatar = null;

    public string $login = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->login = (string) $user->login;
    }

    public function updateProfile(): void
    {
        $user = auth()->user();
        $this->name = trim($this->name);
        $this->email = mb_strtolower(trim($this->email));
        $this->phone = PhoneNumber::normalize($this->phone);

        // Sans login, l'email ou le téléphone est le seul moyen de se connecter.
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [Rule::requiredIf(blank($user->login) && blank($this->phone)), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', PhoneNumber::RULE, Rule::unique('users', 'phone')->ignore($user->id)],
        ], [
            'name.required' => 'Indiquez votre nom.',
            'email.required' => 'Gardez une adresse e-mail ou un téléphone : sans identifiant de connexion, c\'est ce qui vous permet de vous connecter.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        ]);

        $user->update([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
        ]);

        // Rechargement léger (wire:navigate) : le nom et la photo de l'en-tête
        // et du menu de profil, rendus par le layout, suivent la modification.
        $this->flashToast('Profil mis à jour.');
        $this->redirectRoute('account.settings', navigate: true);
    }

    /** La photo est enregistrée dès qu'elle est choisie, comme sur l'application. */
    public function updatedAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width='.ImageOptimizer::MAX_DIMENSION.',max_height='.ImageOptimizer::MAX_DIMENSION],
        ], [
            'avatar.image' => 'Choisissez une image (JPG, PNG ou WebP).',
            'avatar.mimes' => 'Choisissez une image (JPG, PNG ou WebP).',
            'avatar.max' => 'Cette image est trop lourde (4 Mo maximum).',
            'avatar.dimensions' => 'Cette image est trop grande ('.ImageOptimizer::MAX_DIMENSION.' × '.ImageOptimizer::MAX_DIMENSION.' pixels maximum).',
        ]);

        $user = auth()->user();
        $previous = $user->avatar_path;

        $user->update(['avatar_path' => ImageOptimizer::store($this->avatar, 'avatars')]);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        $this->avatar = null;
        $this->flashToast('Photo de profil mise à jour.');
        $this->redirectRoute('account.settings', navigate: true);
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        $this->flashToast('Photo de profil retirée.');
        $this->redirectRoute('account.settings', navigate: true);
    }

    public function updateLogin(): void
    {
        $this->validate([
            'login' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'login')->ignore(auth()->id())],
        ]);

        auth()->user()->update(['login' => $this->login]);

        $this->toast('Identifiant de connexion mis à jour.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password'],
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        // La règle `confirmed` de Laravel attend une clé `{champ}_confirmation`
        // dans les données validées ; les propriétés Livewire étant en
        // camelCase ici, on vérifie la correspondance manuellement.
        if ($this->newPassword !== $this->newPasswordConfirmation) {
            $this->addError('newPasswordConfirmation', 'La confirmation ne correspond pas au nouveau mot de passe.');

            return;
        }

        auth()->user()->update(['password' => $this->newPassword, 'must_change_password' => false]);

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->toast('Mot de passe mis à jour.');
    }

    /** « 07 08 12 44 00 » ou « 0708124400 » → « +2250708124400 ». */
    public function render()
    {
        $user = auth()->user();

        // Même page pour l'équipe d'un pressing et pour la console Super Admin.
        return view('livewire.account.settings', [
            'user' => $user,
            'memberships' => $user->is_super_admin ? collect() : $user->activePressings(),
        ])->layout(
            $user->is_super_admin ? 'layouts.admin' : 'layouts.dashboard',
            ['active' => 'account', 'title' => 'Mon profil'],
        );
    }
}
