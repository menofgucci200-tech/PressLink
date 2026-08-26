<?php

namespace App\Livewire\Admin\Pressings;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Onboarding d'un nouveau pressing par le Super Admin — Phase 7, enrichi
 * pour les propriétaires multi-pressing.
 *
 * - Type "standard" : un seul pressing, sans administrateur (assigné
 *   ensuite depuis le menu Administrateurs).
 * - Type "multi" : un groupe de pressings créés en une fois, tous
 *   rattachés à un même propriétaire (administrateur de chacun d'eux),
 *   ce qui lui donne automatiquement le dashboard mutualisé.
 */
class Create extends Component
{
    public string $type = 'standard';

    // Pressing standard
    public string $name = '';

    public string $code = '';

    public string $phone = '';

    public string $email = '';

    public string $city = '';

    public string $address = '';

    // Groupe de pressings (multi)
    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $ownerPhone = '';

    /** @var array<int, array{name: string, code: string, phone: string, city: string}> */
    public array $pressingRows = [
        ['name' => '', 'code' => '', 'phone' => '', 'city' => ''],
        ['name' => '', 'code' => '', 'phone' => '', 'city' => ''],
    ];

    public ?Pressing $createdPressing = null;

    /** @var Collection<int, Pressing>|null */
    public ?Collection $createdPressings = null;

    public ?string $generatedPassword = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function addPressingRow(): void
    {
        $this->pressingRows[] = ['name' => '', 'code' => '', 'phone' => '', 'city' => ''];
    }

    public function removePressingRow(int $index): void
    {
        unset($this->pressingRows[$index]);
        $this->pressingRows = array_values($this->pressingRows);
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:20', 'alpha_dash', 'unique:pressings,code'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $pressing = DB::transaction(function () {
            $pressing = Pressing::create([
                'name' => $this->name,
                'code' => $this->code !== '' ? mb_strtoupper($this->code) : null,
                'phone' => $this->phone,
                'email' => $this->email ?: null,
                'city' => $this->city ?: null,
                'address' => $this->address ?: null,
            ]);

            $this->createTrialSubscription($pressing);

            return $pressing;
        });

        $this->createdPressing = $pressing;
        $this->reset(['name', 'code', 'phone', 'email', 'city', 'address']);
    }

    public function createGroup(): void
    {
        // Normalisé avant validation pour que la vérification d'unicité (en
        // base et entre les lignes du groupe) porte sur la même casse que
        // celle réellement enregistrée (les codes sont stockés en majuscules).
        foreach ($this->pressingRows as $index => $row) {
            $this->pressingRows[$index]['code'] = mb_strtoupper(trim($row['code']));
        }

        $validator = Validator::make(
            [
                'ownerName' => $this->ownerName,
                'ownerEmail' => $this->ownerEmail,
                'ownerPhone' => $this->ownerPhone,
                'pressingRows' => $this->pressingRows,
            ],
            [
                'ownerName' => ['required', 'string', 'max:100'],
                'ownerEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
                'ownerPhone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/', 'unique:users,phone'],
                'pressingRows' => ['array', 'min:2'],
                'pressingRows.*.name' => ['required', 'string', 'max:150'],
                'pressingRows.*.code' => ['nullable', 'string', 'max:20', 'alpha_dash', 'unique:pressings,code'],
                'pressingRows.*.phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
                'pressingRows.*.city' => ['nullable', 'string', 'max:100'],
            ],
            [
                'pressingRows.min' => 'Un groupe doit contenir au moins 2 pressings (sinon utilisez le type "Pressing standard").',
            ]
        );

        $validator->after(function ($validator) {
            $submittedCodes = collect($this->pressingRows)->pluck('code')->filter(fn ($code) => $code !== '');
            if ($submittedCodes->count() !== $submittedCodes->unique()->count()) {
                $validator->errors()->add('pressingRows', 'Deux pressings du groupe ne peuvent pas avoir le même code.');
            }
        });

        $validator->validate();

        [$pressings, $password] = DB::transaction(function () {
            $password = Str::password(10);

            $owner = User::create([
                'name' => $this->ownerName,
                'email' => $this->ownerEmail,
                'phone' => $this->ownerPhone,
                'password' => $password,
            ]);

            $pressings = collect($this->pressingRows)->map(function (array $row) use ($owner) {
                $pressing = Pressing::create([
                    'name' => $row['name'],
                    'code' => $row['code'] !== '' ? $row['code'] : null,
                    'phone' => $row['phone'],
                    'city' => $row['city'] ?: null,
                ]);

                $this->createTrialSubscription($pressing);

                $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

                return $pressing;
            });

            return [$pressings, $password];
        });

        $this->createdPressings = $pressings;
        $this->generatedPassword = $password;
        $this->reset(['ownerName', 'ownerEmail', 'ownerPhone', 'pressingRows']);
        $this->pressingRows = [
            ['name' => '', 'code' => '', 'phone' => '', 'city' => ''],
            ['name' => '', 'code' => '', 'phone' => '', 'city' => ''],
        ];
    }

    private function createTrialSubscription(Pressing $pressing): void
    {
        Subscription::create([
            'pressing_id' => $pressing->id,
            'plan' => SubscriptionPlan::Starter,
            'status' => SubscriptionStatus::Trialing,
            'orders_limit' => SubscriptionPlan::Starter->ordersLimit(),
            'trial_ends_at' => now()->addDays(14),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addDays(14),
        ]);
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Nouveau pressing'])]
    public function render()
    {
        return view('livewire.admin.pressings.create');
    }
}
