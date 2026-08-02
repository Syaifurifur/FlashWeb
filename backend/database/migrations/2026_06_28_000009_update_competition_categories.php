<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', fn (Blueprint $table) => $table->string('category', 40)->change());
        DB::table('competitions')->where('category','Olahraga')->update(['category'=>'Sport Competition']);
        DB::table('competitions')->where('category','Seni')->update(['category'=>'Talent Competition']);
        DB::table('competitions')->where('category','Akademik')->update(['category'=>'Science Competition']);
    }

    public function down(): void
    {
        DB::table('competitions')->where('category','Sport Competition')->update(['category'=>'Olahraga']);
        DB::table('competitions')->where('category','Talent Competition')->update(['category'=>'Seni']);
        DB::table('competitions')->where('category','Science Competition')->update(['category'=>'Akademik']);
        Schema::table('competitions', fn (Blueprint $table) => $table->enum('category',['Akademik','Olahraga','Seni'])->change());
    }
};
