<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supporter_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_edition_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_code')->unique();
            $table->string('full_name', 120);
            $table->enum('grade', ['X', 'XI', 'XII']);
            $table->string('school_name', 180);
            $table->enum('gender', ['male', 'female']);
            $table->string('email', 150);
            $table->string('whatsapp', 20);
            $table->boolean('interested_in_college');
            $table->enum('payment_method', ['cash', 'transfer']);
            $table->string('payment_proof_path')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('verification_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_edition_id', 'status']);
            $table->index(['event_edition_id', 'payment_method']);
            $table->index(['event_edition_id', 'school_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supporter_tickets');
    }
};
