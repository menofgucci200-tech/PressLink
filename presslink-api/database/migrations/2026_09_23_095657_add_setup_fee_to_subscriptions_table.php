<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('setup_fee_fcfa')->nullable()->after('billing_cycle');
            $table->timestamp('setup_fee_paid_at')->nullable()->after('setup_fee_fcfa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['setup_fee_fcfa', 'setup_fee_paid_at']);
        });
    }
};
