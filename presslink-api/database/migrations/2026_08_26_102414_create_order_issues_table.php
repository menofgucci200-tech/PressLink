<?php

use App\Enums\OrderIssueStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signalement d'un problème sur une commande par le client
 * (ex. article manquant, article qui ne lui appartient pas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('status')->default(OrderIssueStatus::Open->value);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_issues');
    }
};
