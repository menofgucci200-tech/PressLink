<?php

use App\Enums\PressingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pressings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->comment('Code public — ex. PE-4821, utilisé par les clients pour rejoindre le pressing');
            $table->string('logo_path')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->text('description')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('status')->default(PressingStatus::Active->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pressings');
    }
};
