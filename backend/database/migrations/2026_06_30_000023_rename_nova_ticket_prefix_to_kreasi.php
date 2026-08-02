<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $this->replacePrefix('NOVA-', 'BSIFLASH-');
    }

    public function down(): void
    {
        $this->replacePrefix('BSIFLASH-', 'NOVA-');
    }

    private function replacePrefix(string $from, string $to): void
    {
        DB::table('registrations')
            ->where('ticket_code', 'like', $from.'%')
            ->orderBy('id')
            ->each(function ($registration) use ($from, $to) {
                DB::table('registrations')->where('id', $registration->id)->update([
                    'ticket_code' => $to.substr($registration->ticket_code, strlen($from)),
                ]);
            });
    }
};
