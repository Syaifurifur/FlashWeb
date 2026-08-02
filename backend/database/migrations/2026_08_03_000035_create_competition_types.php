<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competition_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->string('category_group', 40);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        foreach (['Sport Competition', 'Talent Competition', 'Science Competition'] as $order => $name) {
            DB::table('competition_types')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'category_group' => $name,
                'description' => 'Jenis lomba bawaan untuk kelompok '.$name.'.',
                'sort_order' => $order + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('competitions', function (Blueprint $table) {
            $table->foreignId('competition_type_id')->nullable()->after('category')
                ->constrained('competition_types')->restrictOnDelete();
        });

        DB::table('competition_types')->get()->each(function ($type) {
            DB::table('competitions')
                ->where('category', $type->category_group)
                ->update(['competition_type_id' => $type->id]);
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competition_type_id');
        });

        Schema::dropIfExists('competition_types');
    }
};
