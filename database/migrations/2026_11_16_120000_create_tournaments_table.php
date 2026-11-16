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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arbiter_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('pairing_type');
            $table->string('rating_mode')->default('average');
            $table->unsignedSmallInteger('rounds_count');
            $table->unsignedSmallInteger('games_per_round')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index('pairing_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
