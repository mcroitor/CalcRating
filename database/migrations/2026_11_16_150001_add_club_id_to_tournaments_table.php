<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('club_id')
                ->nullable()
                ->after('id')
                ->constrained('clubs')
                ->restrictOnDelete();
        });

        if (DB::table('tournaments')->whereNull('club_id')->exists()) {
            $now = now();
            $defaultClubId = DB::table('clubs')->insertGetId([
                'name' => 'Default Chess Club',
                'code' => 'DEFAULT',
                'status' => 'active',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $adminId = DB::table('users')
                ->where('role', 'admin')
                ->where('is_blocked', false)
                ->orderBy('id')
                ->value('id');

            if ($adminId !== null) {
                DB::table('club_user')->insert([
                    'club_id' => $defaultClubId,
                    'user_id' => $adminId,
                    'role' => 'manager',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('tournaments')
                ->whereNull('club_id')
                ->update(['club_id' => $defaultClubId]);
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
        });
    }
};
