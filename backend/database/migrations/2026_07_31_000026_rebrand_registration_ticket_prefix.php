<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->replacePrefix('KREASI-', 'BSIFLASH-');
    }

    public function down(): void
    {
        $this->replacePrefix('BSIFLASH-', 'KREASI-');
    }

    private function replacePrefix(string $from, string $to): void
    {
        if (!Schema::hasTable('registrations')) {
            return;
        }

        DB::table('registrations')
            ->select('id', 'ticket_code')
            ->where('ticket_code', 'like', $from.'%')
            ->orderBy('id')
            ->chunkById(100, function ($registrations) use ($from, $to) {
                foreach ($registrations as $registration) {
                    DB::table('registrations')->where('id', $registration->id)->update([
                        'ticket_code' => $to.substr($registration->ticket_code, strlen($from)),
                    ]);
                }
            });
    }
};
