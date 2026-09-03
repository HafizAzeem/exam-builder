<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('model_name', 100)->default('gemini-2.5-flash');
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->unsignedInteger('max_tokens')->default(8192);
            $table->text('prompt_template')->nullable();
            $table->unsignedInteger('chunk_size')->default(8000);
            $table->unsignedTinyInteger('retry_count')->default(3);
            $table->boolean('enable_queue')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->enum('book_type', ['text_book', 'past_paper', 'additional_questions']);
            $table->string('board', 100)->nullable();
            $table->year('year')->nullable();
            $table->enum('session', ['morning', 'evening'])->nullable();
            $table->string('language', 50)->default('english');
            $table->string('original_filename');
            $table->string('stored_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('status', [
                'uploaded',
                'extracting',
                'processing',
                'review',
                'importing',
                'completed',
                'failed',
            ])->default('uploaded');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->unsignedInteger('processed_chunks')->default(0);
            $table->unsignedInteger('questions_found')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_import_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_import_id')->constrained('ai_imports')->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter_number')->nullable();
            $table->string('chapter_title', 500)->nullable();
            $table->string('topic_title', 500)->nullable();
            $table->foreignId('chapter_id')->nullable()->constrained('chapters')->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->enum('match_status', [
                'matched',
                'unmatched_chapter',
                'unmatched_topic',
                'manual',
            ])->default('unmatched_chapter');
            $table->enum('type', ['mcq', 'short', 'long', 'fill', 'truefalse']);
            $table->enum('source', ['exercise', 'additional', 'past_paper']);
            $table->text('text_en')->nullable();
            $table->text('text_ur')->nullable();
            $table->json('mcq_options')->nullable();
            $table->json('parts')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'imported',
                'duplicate',
                'failed',
            ])->default('pending');
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('duplicate_of_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_import_id')->constrained('ai_imports')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->longText('prompt')->nullable();
            $table->longText('response')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_import_logs');
        Schema::dropIfExists('ai_import_questions');
        Schema::dropIfExists('ai_imports');
        Schema::dropIfExists('ai_settings');
    }
};
