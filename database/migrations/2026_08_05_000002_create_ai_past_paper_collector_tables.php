<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->text('gemini_api_key')->nullable()->after('enable_queue');
            $table->text('google_search_api_key')->nullable()->after('gemini_api_key');
            $table->string('google_cse_id', 100)->nullable()->after('google_search_api_key');
            $table->unsignedInteger('max_urls_per_search')->default(10)->after('google_cse_id');
            $table->unsignedInteger('max_pages_per_source')->default(20)->after('max_urls_per_search');
            $table->unsignedInteger('search_timeout')->default(30)->after('max_pages_per_source');
            $table->unsignedTinyInteger('collector_retry_attempts')->default(3)->after('search_timeout');
            $table->decimal('duplicate_similarity_threshold', 4, 3)->default(0.850)->after('collector_retry_attempts');
            $table->unsignedInteger('queue_size')->default(5)->after('duplicate_similarity_threshold');
            $table->unsignedInteger('max_source_bytes')->default(15_000_000)->after('queue_size');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->string('difficulty', 20)->nullable()->after('is_active');
            $table->unsignedTinyInteger('estimated_marks')->nullable()->after('difficulty');
        });

        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->foreignId('ai_paper_source_id')->nullable()->after('ai_import_id');
            $table->decimal('confidence_score', 5, 4)->nullable()->after('raw_payload');
            $table->string('difficulty', 20)->nullable()->after('confidence_score');
            $table->unsignedTinyInteger('estimated_marks')->nullable()->after('difficulty');
            $table->string('source_url', 1000)->nullable()->after('estimated_marks');
            $table->text('source_excerpt')->nullable()->after('source_url');
            $table->decimal('duplicate_score', 5, 4)->nullable()->after('source_excerpt');
            $table->json('duplicate_diff')->nullable()->after('duplicate_score');
        });

        Schema::create('ai_paper_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('ai_import_id')->nullable()->constrained('ai_imports')->nullOnDelete();
            $table->string('board', 100)->default('Lahore Board');
            $table->year('year');
            $table->enum('session', ['morning', 'evening'])->nullable();
            $table->enum('paper_type', ['objective', 'subjective', 'complete'])->default('complete');
            $table->string('language', 50)->default('english');
            $table->string('country', 50)->default('Pakistan');
            $table->unsignedInteger('max_results')->default(10);
            $table->string('keywords_override', 500)->nullable();
            $table->json('generated_queries')->nullable();
            $table->enum('status', [
                'queued',
                'searching',
                'collecting_sources',
                'downloading',
                'extracting',
                'processing',
                'classifying',
                'review',
                'importing',
                'completed',
                'failed',
            ])->default('queued');
            $table->string('progress_stage', 50)->default('queued');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('urls_visited')->default(0);
            $table->unsignedInteger('successful_sources')->default(0);
            $table->unsignedInteger('failed_sources')->default(0);
            $table->unsignedInteger('ocr_required_sources')->default(0);
            $table->unsignedInteger('questions_found')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_paper_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_paper_collection_id')->constrained('ai_paper_collections')->cascadeOnDelete();
            $table->string('url', 1000);
            $table->string('normalized_url', 1000)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('snippet')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('stored_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('status', [
                'discovered',
                'downloading',
                'downloaded',
                'extracting',
                'extracted',
                'processing',
                'processed',
                'ocr_required',
                'ignored',
                'failed',
            ])->default('discovered');
            $table->longText('extracted_text')->nullable();
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('questions_extracted')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ai_paper_collection_id', 'status']);
            $table->index('content_hash');
        });

        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->foreign('ai_paper_source_id')
                ->references('id')
                ->on('ai_paper_sources')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->dropForeign(['ai_paper_source_id']);
            $table->dropColumn([
                'ai_paper_source_id',
                'confidence_score',
                'difficulty',
                'estimated_marks',
                'source_url',
                'source_excerpt',
                'duplicate_score',
                'duplicate_diff',
            ]);
        });

        Schema::dropIfExists('ai_paper_sources');
        Schema::dropIfExists('ai_paper_collections');

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'estimated_marks']);
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'gemini_api_key',
                'google_search_api_key',
                'google_cse_id',
                'max_urls_per_search',
                'max_pages_per_source',
                'search_timeout',
                'collector_retry_attempts',
                'duplicate_similarity_threshold',
                'queue_size',
                'max_source_bytes',
            ]);
        });
    }
};
