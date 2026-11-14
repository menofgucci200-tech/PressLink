<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de ramassage envoyées depuis l'appli client à l'un de ses
 * pressings. L'adresse est recopiée : elle reste celle de la demande même
 * si le client la modifie ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('address_label', 40);
            $table->string('commune', 60);
            $table->string('neighborhood', 80)->nullable();
            $table->string('landmark');
            $table->date('pickup_date');
            $table->string('slot_from', 5);
            $table->string('slot_to', 5);
            $table->boolean('rescheduled')->default(false);
            $table->unsignedSmallInteger('items_estimate')->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('pickup_fee_fcfa')->default(0);
            $table->string('refusal_reason')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['pressing_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('pickup_request_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_request_photos');
        Schema::dropIfExists('pickup_requests');
    }
};
