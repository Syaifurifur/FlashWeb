<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_editions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        $editionId = DB::table('event_editions')->insertGetId([
            'year' => 2027,
            'name' => 'BSI Flash 2027',
            'slug' => 'bsi-flash-2027',
            'status' => 'active',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('competitions', function (Blueprint $table) {
            $table->foreignId('event_edition_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->index(['event_edition_id', 'is_featured']);
        });
        Schema::table('competition_venues', function (Blueprint $table) {
            $table->foreignId('event_edition_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->index(['event_edition_id', 'is_active', 'city']);
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('event_edition_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->index(['event_edition_id', 'status']);
        });
        Schema::table('site_contents', function (Blueprint $table) {
            $table->dropUnique('site_contents_key_unique');
            $table->foreignId('event_edition_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['event_edition_id', 'key']);
        });
        Schema::table('competition_notifications', function (Blueprint $table) {
            $table->foreignId('event_edition_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['event_edition_id', 'published_at']);
        });

        DB::table('competitions')->update(['event_edition_id' => $editionId]);
        DB::table('competition_venues')->update(['event_edition_id' => $editionId]);
        DB::table('site_contents')->update(['event_edition_id' => $editionId]);
        DB::table('competition_notifications')->update(['event_edition_id' => $editionId]);
        DB::table('competitions')->select('id')->orderBy('id')->each(function ($competition) use ($editionId) {
            DB::table('registrations')->where('competition_id', $competition->id)->update(['event_edition_id' => $editionId]);
        });
    }

    public function down(): void
    {
        Schema::table('competition_notifications', function (Blueprint $table) {
            $table->dropIndex(['event_edition_id', 'published_at']);
            $table->dropConstrainedForeignId('event_edition_id');
        });
        Schema::table('site_contents', function (Blueprint $table) {
            $table->dropUnique(['event_edition_id', 'key']);
            $table->dropConstrainedForeignId('event_edition_id');
            $table->unique('key');
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['event_edition_id', 'status']);
            $table->dropConstrainedForeignId('event_edition_id');
        });
        Schema::table('competition_venues', function (Blueprint $table) {
            $table->dropIndex(['event_edition_id', 'is_active', 'city']);
            $table->dropConstrainedForeignId('event_edition_id');
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropIndex(['event_edition_id', 'is_featured']);
            $table->dropConstrainedForeignId('event_edition_id');
        });
        Schema::dropIfExists('event_editions');
    }
};
