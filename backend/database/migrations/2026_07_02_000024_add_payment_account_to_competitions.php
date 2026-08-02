<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('bank_name')->default('Bank Mandiri')->after('fee');
            $table->string('bank_account_number', 80)->default('123 000 111 3804')->after('bank_name');
            $table->string('bank_account_holder')->default('Yayasan Indonesia Nusa Mandiri')->after('bank_account_number');
            $table->string('payment_note', 500)->nullable()->after('bank_account_holder');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_holder', 'payment_note']);
        });
    }
};
