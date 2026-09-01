<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_editions', function (Blueprint $table) {
            $table->decimal('supporter_ticket_price', 12, 2)->default(0)->after('ends_at');
            $table->string('supporter_bank_name', 120)->nullable()->after('supporter_ticket_price');
            $table->string('supporter_bank_account_number', 80)->nullable()->after('supporter_bank_name');
            $table->string('supporter_bank_account_holder', 180)->nullable()->after('supporter_bank_account_number');
            $table->string('supporter_payment_note', 500)->nullable()->after('supporter_bank_account_holder');
        });

        Schema::table('supporter_tickets', function (Blueprint $table) {
            $table->decimal('ticket_price', 12, 2)->default(0)->after('interested_in_college');
        });

        DB::table('event_editions')->orderBy('id')->get()->each(function ($edition) {
            $account = DB::table('competitions')
                ->where('event_edition_id', $edition->id)
                ->whereNotNull('bank_account_number')
                ->orderBy('id')
                ->first(['bank_name', 'bank_account_number', 'bank_account_holder', 'payment_note']);

            if ($account) {
                DB::table('event_editions')->where('id', $edition->id)->update([
                    'supporter_bank_name' => $account->bank_name,
                    'supporter_bank_account_number' => $account->bank_account_number,
                    'supporter_bank_account_holder' => $account->bank_account_holder,
                    'supporter_payment_note' => $account->payment_note,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('supporter_tickets', function (Blueprint $table) {
            $table->dropColumn('ticket_price');
        });

        Schema::table('event_editions', function (Blueprint $table) {
            $table->dropColumn([
                'supporter_ticket_price',
                'supporter_bank_name',
                'supporter_bank_account_number',
                'supporter_bank_account_holder',
                'supporter_payment_note',
            ]);
        });
    }
};
