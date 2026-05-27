<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('chapters')->cascadeOnDelete();
            $table->enum('type', ['mcq', 'short', 'long', 'fill', 'truefalse']);
            $table->enum('source', ['exercise', 'additional', 'past_paper']);
            $table->text('text_en')->nullable();
            $table->text('text_ur')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->boolean('has_parts')->default(false);
            $table->foreignId('parent_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('mcq_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('option_a_en');
            $table->text('option_a_ur')->nullable();
            $table->text('option_b_en');
            $table->text('option_b_ur')->nullable();
            $table->text('option_c_en');
            $table->text('option_c_ur')->nullable();
            $table->text('option_d_en');
            $table->text('option_d_ur')->nullable();
            $table->enum('correct_option', ['a', 'b', 'c', 'd']);
        });

        Schema::create('past_paper_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('board_name', 100);
            $table->year('year');
            $table->enum('session', ['morning', 'evening'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_paper_tags');
        Schema::dropIfExists('mcq_options');
        Schema::dropIfExists('questions');
    }
};

