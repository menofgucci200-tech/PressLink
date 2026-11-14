<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ramassage et livraison, lot 3 : le ramassage fait devient une commande
 * (dépôt rapide), le client se fait livrer une commande prête, le coursier
 * encaisse à la porte. Les rôles Gérant et Employé reçoivent l'accès
 * « Tournées », comme ils ont déjà celui d'encaisser au comptoir.
 */
return new class extends Migration
{
    private const PERMISSION = 'deliveries.run';

    public function up(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->timestamp('picked_up_at')->nullable()->after('responded_at');
            $table->foreignId('picked_up_by')->nullable()->after('picked_up_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('scheduled');
            $table->string('address_label', 40);
            $table->string('commune', 60);
            $table->string('neighborhood', 80)->nullable();
            $table->string('landmark');
            $table->date('delivery_date');
            $table->string('slot_from', 5);
            $table->string('slot_to', 5);
            $table->boolean('rescheduled')->default(false);
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('delivery_fee_fcfa')->default(0);
            $table->string('cancel_reason')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['pressing_id', 'status', 'delivery_date']);
            $table->index(['order_id', 'status']);
        });

        DB::table('roles')->whereIn('key', ['manager', 'employee'])->orderBy('id')->each(function (object $role): void {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            if (! in_array(self::PERMISSION, $permissions, true)) {
                $permissions[] = self::PERMISSION;
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deliveries');

        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('picked_up_by');
            $table->dropColumn('picked_up_at');
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
