<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Le propriétaire d'un groupe reçoit chaque mois le rapport PDF par e-mail. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('group_report_opt_in')->default(false)->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('group_report_opt_in');
        });
    }
};
