<?php

namespace App\Livewire\Admin\Site;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Site vitrine : témoignages de gérants affichés sur les pages Pour les
 * pressings et Nos offres. Un témoignage n'est publié qu'avec l'accord
 * écrit du gérant ; sans témoignage, le bloc n'apparaît pas.
 */
class Index extends Component
{
    use ShowsToasts;

    /** @var array{name: string, pressing: string, commune: string, quote: string, consent: bool} */
    public array $form = ['name' => '', 'pressing' => '', 'commune' => '', 'quote' => '', 'consent' => false];

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    /** @return list<array{id: string, name: string, pressing: string, commune: string, quote: string, consent: bool, published: bool}> */
    private function testimonials(): array
    {
        $all = Setting::read('testimonials', []);

        return array_values(array_filter(is_array($all) ? $all : [], 'is_array'));
    }

    /** @param  list<array<string, mixed>>  $testimonials */
    private function store(array $testimonials): void
    {
        Setting::write('testimonials', array_values($testimonials));
    }

    public function add(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->validate([
            'form.name' => ['required', 'string', 'max:80'],
            'form.pressing' => ['required', 'string', 'max:120'],
            'form.commune' => ['nullable', 'string', 'max:80'],
            'form.quote' => ['required', 'string', 'min:20', 'max:400'],
            'form.consent' => ['accepted'],
        ], [
            'form.consent.accepted' => 'Cochez la case : un témoignage n\'est publié qu\'avec l\'accord écrit du gérant.',
        ], [
            'form.name' => 'nom', 'form.pressing' => 'pressing', 'form.commune' => 'commune', 'form.quote' => 'témoignage',
        ]);

        $this->store([...$this->testimonials(), [
            'id' => (string) Str::uuid(),
            'name' => trim($this->form['name']),
            'pressing' => trim($this->form['pressing']),
            'commune' => trim($this->form['commune']),
            'quote' => trim($this->form['quote']),
            'consent' => true,
            'published' => true,
            'added_at' => now()->toDateString(),
        ]]);

        $this->reset('form');
        $this->toast('Témoignage publié sur le site.');
    }

    public function togglePublished(string $id): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->store(array_map(fn (array $t) => ($t['id'] ?? null) === $id ? [...$t, 'published' => ! ($t['published'] ?? true)] : $t, $this->testimonials()));
        $this->toast('Témoignage mis à jour.');
    }

    public function remove(string $id): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->store(array_filter($this->testimonials(), fn (array $t) => ($t['id'] ?? null) !== $id));
        $this->toast('Témoignage supprimé.');
    }

    #[Layout('layouts.admin', ['active' => 'site', 'title' => 'Site vitrine'])]
    public function render(): View
    {
        return view('livewire.admin.site.index', ['testimonials' => $this->testimonials()]);
    }
}
