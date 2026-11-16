<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 32)->nullable()->unique();
            $table->string('city', 120)->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('club_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['club_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('club_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->date('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_player');
        Schema::dropIfExists('club_user');
        Schema::dropIfExists('clubs');
    }
};
