<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_loa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_edition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('scholarship_name', 200)->default('Beasiswa Pendidikan BSI');
            $table->longText('body_template');
            $table->string('number_pattern', 200)->default('{{sequence}}/LOA-BEASISWA/BSI-FLASH/{{year}}');
            $table->string('signing_city', 120)->nullable();
            $table->string('signatory_name', 160)->nullable();
            $table->string('signatory_position', 160)->nullable();
            $table->string('background_path', 1000)->nullable();
            $table->string('signature_path', 1000)->nullable();
            $table->string('reference_path', 1000)->nullable();
            $table->string('reference_name', 255)->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_edition_id', 'is_active']);
        });

        Schema::create('scholarship_loa_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_loa_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('competition_result_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('document_number', 220)->index();
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_loa_issuances');
        Schema::dropIfExists('scholarship_loa_templates');
    }
};
