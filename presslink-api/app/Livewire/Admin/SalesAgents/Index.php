<?php

namespace App\Livewire\Admin\SalesAgents;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Pressing;
use App\Models\SalesAgent;
use App\Models\SalesAgentClaim;
use App\Models\Setting;
use App\Models\User;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Commerciaux de terrain — Super Admin : création des comptes, taux de
 * commission, suspension, et validation des signalements « ce pressing
 * vient de moi » (rattachement au commercial).
 */
class Index extends Component
{
    use ShowsToasts;

    public bool $showCreateForm = false;

    public string $name = '';

    public string $phone = '';

    public string $password = '';

    public string $rate = '';

    public string $defaultRate = '';

    /** @var array<int, string> pressing choisi pour chaque signalement en attente */
    public array $claimPressing = [];

    /** @var array<int, string> taux en cours de modification, par commercial */
    public array $rates = [];

    public ?int $createdAgentId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->defaultRate = (string) SalesAgent::defaultCommissionRate();
    }

    public function create(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->phone = $this->phone === '' ? '' : PhoneNumber::normalize($this->phone);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', new IvorianPhone, 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [
            'phone.unique' => 'Ce numéro a déjà un compte PressLink.',
        ]);

        $agent = DB::transaction(function () {
            $user = User::create([
                'name' => trim($this->name),
                'phone' => $this->phone,
                'password' => $this->password,
                'must_change_password' => true,
            ]);

            return SalesAgent::create([
                'user_id' => $user->id,
                'code' => SalesAgent::generateCode($this->name),
                'commission_rate' => $this->rate === '' ? null : (int) $this->rate,
            ]);
        });

        $this->createdAgentId = $agent->id;
        $this->reset(['name', 'phone', 'password', 'rate', 'showCreateForm']);
    }

    public function saveDefaultRate(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->validate(['defaultRate' => ['required', 'integer', 'min:0', 'max:100']]);

        Setting::write('sales_commission_rate', (int) $this->defaultRate);
        $this->toast('Taux commun enregistré : '.(int) $this->defaultRate.' %.');
    }

    public function saveRate(int $agentId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $agent = SalesAgent::findOrFail($agentId);
        $value = trim($this->rates[$agentId] ?? '');

        $this->validate(["rates.{$agentId}" => ['nullable', 'integer', 'min:0', 'max:100']], [], ["rates.{$agentId}" => 'taux']);

        $agent->update(['commission_rate' => $value === '' ? null : (int) $value]);
        unset($this->rates[$agentId]);
        $this->toast("Taux de {$agent->user->name} : {$agent->commissionRate()} %.");
    }

    public function toggleSuspension(int $agentId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $agent = SalesAgent::findOrFail($agentId);
        $agent->update(['suspended_at' => $agent->isActive() ? now() : null]);

        $this->toast($agent->isActive() ? "{$agent->user->name} peut de nouveau se connecter." : "{$agent->user->name} est suspendu : il ne peut plus se connecter.");
    }

    public function approveClaim(int $claimId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $claim = SalesAgentClaim::pending()->findOrFail($claimId);
        $pressingId = (int) ($this->claimPressing[$claimId] ?? $claim->pressing_id);
        $pressing = Pressing::find($pressingId);

        if ($pressing === null) {
            $this->addError("claimPressing.{$claimId}", 'Choisissez le pressing à rattacher.');

            return;
        }

        DB::transaction(function () use ($claim, $pressing) {
            $pressing->attachToSalesAgent($claim->salesAgent);
            $claim->update(['pressing_id' => $pressing->id, 'status' => SalesAgentClaim::APPROVED, 'handled_by' => auth()->id(), 'handled_at' => now()]);
        });

        $this->toast("{$pressing->name} est rattaché à {$claim->salesAgent->user->name}.");
    }

    public function declineClaim(int $claimId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        SalesAgentClaim::pending()->findOrFail($claimId)
            ->update(['status' => SalesAgentClaim::DECLINED, 'handled_by' => auth()->id(), 'handled_at' => now()]);

        $this->toast('Signalement refusé.', 'info');
    }

    #[Layout('layouts.admin', ['active' => 'sales', 'title' => 'Commerciaux'])]
    public function render(): View
    {
        $agents = SalesAgent::with('user')
            ->withSum(['commissions as unpaid_fcfa' => fn ($query) => $query->whereNull('paid_at')], 'amount_fcfa')
            ->withCount([
                'pressings',
                'pressings as pressings_this_month' => fn ($query) => $query->where('sales_agent_attached_at', '>=', now()->startOfMonth()),
            ])
            ->orderByRaw('suspended_at is not null')
            ->latest()
            ->get();

        return view('livewire.admin.sales-agents.index', [
            'agents' => $agents,
            'claims' => SalesAgentClaim::pending()->with(['salesAgent.user', 'pressing.salesAgent.user'])->oldest()->get(),
            'pressingOptions' => Pressing::query()->withoutSandboxes()->withoutDemo()->orderBy('name')->get(['id', 'name', 'city', 'sales_agent_id']),
            'createdAgent' => $this->createdAgentId ? SalesAgent::with('user')->find($this->createdAgentId) : null,
        ]);
    }
}
