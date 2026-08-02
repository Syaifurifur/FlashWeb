<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->json('guides')->nullable()->after('requirements');
        });

        DB::table('competitions')->orderBy('id')->each(function ($competition) {
            $requirements = json_decode($competition->requirements ?? '[]', true);
            if (! is_array($requirements) || count($requirements) === 0) {
                return;
            }

            DB::table('competitions')->where('id', $competition->id)->update([
                'guides' => json_encode([[
                    'title' => 'Ketentuan Umum',
                    'content' => implode("\n", $requirements),
                ]], JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('guides');
        });
    }
};
