<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('chapters')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('title_en', 500);
            $table->string('title_ur', 500)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->unique(['chapter_id', 'code']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('topic_id')
                ->nullable()
                ->after('chapter_id')
                ->constrained('topics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
        });

        Schema::dropIfExists('topics');
    }
};
