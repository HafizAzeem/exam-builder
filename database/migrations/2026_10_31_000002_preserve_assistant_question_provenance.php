<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_options', fn (Blueprint $table) => $table->enum('correct_option', ['a', 'b', 'c', 'd'])->nullable()->change());
        Schema::table('questions', fn (Blueprint $table) => $table->dateTime('source_published_at')->nullable());
        Schema::table('ai_import_questions', fn (Blueprint $table) => $table->dateTime('source_published_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn('source_published_at'));
        Schema::table('ai_import_questions', fn (Blueprint $table) => $table->dropColumn('source_published_at'));
        // Retain nullable answer keys rather than fabricate answers for existing questions.
    }
};
