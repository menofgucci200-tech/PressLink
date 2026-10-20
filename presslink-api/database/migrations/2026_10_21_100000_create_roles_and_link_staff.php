<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles et permissions par pressing : chaque pressing reçoit « Propriétaire »,
 * « Gérant » et « Employé » ; les administrateurs actuels deviennent
 * propriétaires, les employés restent employés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pressing_id')->constrained()->cascadeOnDelete();
            $table->string('key', 20)->nullable();
            $table->string('name', 60);
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['pressing_id', 'name']);
            $table->index(['pressing_id', 'key']);
        });

        Schema::table('pressing_users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        $now = now();
        foreach (DB::table('pressings')->pluck('id') as $pressingId) {
            foreach (Role::defaults() as $key => $role) {
                $roleId = DB::table('roles')->insertGetId([
                    'pressing_id' => $pressingId,
                    'key' => $key,
                    'name' => $role['name'],
                    'permissions' => json_encode($role['permissions']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $legacy = match ($key) {
                    Role::OWNER => 'admin',
                    Role::EMPLOYEE => 'employee',
                    default => null,
                };
                if ($legacy !== null) {
                    DB::table('pressing_users')->where('pressing_id', $pressingId)->where('role', $legacy)->update(['role_id' => $roleId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('pressing_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
