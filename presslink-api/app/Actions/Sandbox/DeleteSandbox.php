<?php

namespace App\Actions\Sandbox;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Pressing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Supprime un pressing de test et tout ce qui lui appartient : ses clients
 * fictifs (et leurs notifications), les comptes de son équipe qui ne servent
 * nulle part ailleurs, son logo, puis le pressing lui-même (commandes,
 * services, caisse, journal… partent en cascade). La fiche du test
 * (SandboxTrial) reste, pour le suivi commercial.
 */
class DeleteSandbox
{
    public function handle(Pressing $pressing): void
    {
        if (! $pressing->isSandbox()) {
            throw new InvalidArgumentException("Le pressing {$pressing->code} n'est pas un pressing de test.");
        }

        ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($pressing) {
            $customerIds = Customer::where('sandbox_pressing_id', $pressing->id)->pluck('id');
            DB::table('notifications')
                ->where('notifiable_type', (new Customer)->getMorphClass())
                ->whereIn('notifiable_id', $customerIds)
                ->delete();

            $staff = $pressing->staff()->get();

            // Les commandes partent avec les clients puis avec le pressing.
            Customer::whereKey($customerIds)->delete();
            $pressing->delete();

            $staff->filter(fn ($user) => ! $user->is_super_admin && ! $user->pressings()->exists())->each->delete();
        }));

        if ($pressing->logo_path !== null) {
            Storage::disk('public')->delete($pressing->logo_path);
        }
    }
}
