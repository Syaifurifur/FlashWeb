<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('pic_slots')->default(1)->after('supervisor_user_id');
            $table->unsignedTinyInteger('supervisor_slots')->default(1)->after('pic_slots');
        });

        Schema::create('competition_session_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['competition_session_id', 'user_id']);
            $table->index(['competition_session_id', 'role']);
        });

        DB::table('competition_sessions')->orderBy('id')->get()->each(function ($session) {
            foreach ([['pic', $session->pic_user_id], ['spv', $session->supervisor_user_id]] as [$role, $userId]) {
                if (! $userId) continue;
                DB::table('competition_session_staff')->insert([
                    'competition_session_id'=>$session->id,
                    'user_id'=>$userId,
                    'role'=>$role,
                    'sort_order'=>0,
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_session_staff');
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->dropColumn(['pic_slots', 'supervisor_slots']);
        });
    }
};
