<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RB-03 : une commande appartient obligatoirement à 1 pressing + 1 client.
 * RB-10 : soft delete — pas de suppression physique, on archive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique()->comment('Ex. PL-000124');
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(OrderStatus::Recue->value);
            $table->unsignedInteger('total_fcfa')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('dropped_off_at');
            $table->timestamp('expected_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recovered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pressing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
