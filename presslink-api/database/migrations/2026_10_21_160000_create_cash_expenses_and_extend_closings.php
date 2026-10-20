<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dépenses de caisse (sorties d'espèces de la journée : achat de produits,
 * transport…) et clôture qui en tient compte : espèces encaissées − dépenses
 * = espèces attendues dans la caisse, comparées aux espèces comptées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('amount_fcfa');
            $table->string('label', 150);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pressing_id', 'business_date']);
        });

        Schema::table('cash_closings', function (Blueprint $table) {
            $table->integer('cash_received_fcfa')->default(0)->after('total_fcfa');
            $table->unsignedInteger('expenses_fcfa')->default(0)->after('cash_received_fcfa');
            $table->integer('expected_cash_fcfa')->change();
        });

        // Clôtures existantes : sans dépenses, les espèces encaissées étaient les espèces attendues.
        DB::table('cash_closings')->update(['cash_received_fcfa' => DB::raw('expected_cash_fcfa')]);

        // Les gérants existants enregistrent les dépenses ; « ses propres encaissements » disparaît.
        DB::table('roles')->orderBy('id')->each(function (object $role) {
            $permissions = array_values(array_diff(json_decode($role->permissions ?? '[]', true) ?: [], ['cash.own_payments']));
            if ($role->key === 'manager' && ! in_array('cash.expenses', $permissions, true)) {
                $permissions[] = 'cash.expenses';
            }
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        });
    }

    public function down(): void
    {
        Schema::table('cash_closings', function (Blueprint $table) {
            $table->dropColumn(['cash_received_fcfa', 'expenses_fcfa']);
        });

        Schema::dropIfExists('cash_expenses');
    }
};
