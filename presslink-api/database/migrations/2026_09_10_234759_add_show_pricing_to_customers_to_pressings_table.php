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
        Schema::table('pressings', function (Blueprint $table) {
            $table->boolean('show_pricing_to_customers')->default(false)->after('opening_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pressings', function (Blueprint $table) {
            $table->dropColumn('show_pricing_to_customers');
        });
    }
};
