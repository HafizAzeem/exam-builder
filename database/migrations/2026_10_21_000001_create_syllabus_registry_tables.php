<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_editions', function (Blueprint $table) {
            $table->id();
            $table->string('academic_session', 9);
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('board', 100)->default('Punjab Boards');
            $table->string('group_name', 100)->nullable();
            $table->string('textbook_edition', 150)->nullable();
            $table->string('effective_session', 9);
            $table->string('status', 20)->default('unverified');
            $table->string('source_url', 1000)->nullable();
            $table->date('verified_at')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['academic_session', 'grade_id', 'subject_id', 'board', 'group_name', 'textbook_edition'], 'syllabus_edition_unique');
            $table->index(['grade_id', 'subject_id', 'effective_session', 'status'], 'syllabus_applicability_idx');
        });

        Schema::create('syllabus_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_edition_id')->constrained('syllabus_editions')->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter_number');
            $table->string('chapter_title', 500);
            $table->json('topics')->nullable();
            $table->string('status', 20)->default('unverified');
            $table->string('change_type', 20)->default('unchanged');
            $table->string('source_url', 1000)->nullable();
            $table->date('verified_at')->nullable();
            $table->text('change_notes')->nullable();
            $table->timestamps();
            $table->unique(['syllabus_edition_id', 'chapter_number'], 'syllabus_chapter_number_unique');
            $table->index(['syllabus_edition_id', 'status', 'change_type']);
        });

        Schema::create('syllabus_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_chapter_id')->constrained('syllabus_chapters')->cascadeOnDelete();
            $table->string('kind', 30); // deleted_topic, excluded_exercise_question
            $table->string('reference', 255)->nullable();
            $table->text('content');
            $table->string('source_url', 1000)->nullable();
            $table->date('verified_at')->nullable();
            $table->timestamps();
            $table->index(['syllabus_chapter_id', 'kind']);
        });

        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->foreignId('syllabus_chapter_id')->nullable()->after('chapter_id')->constrained('syllabus_chapters')->nullOnDelete();
            $table->string('syllabus_verification_status', 20)->default('unverified')->after('syllabus_chapter_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('syllabus_chapter_id')->nullable()->after('chapter_id')->constrained('syllabus_chapters')->nullOnDelete();
            $table->string('source_url', 1000)->nullable()->after('source');
            $table->string('syllabus_verification_status', 20)->default('unverified')->after('source_url');
        });

    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syllabus_chapter_id');
            $table->dropColumn(['source_url', 'syllabus_verification_status']);
        });
        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syllabus_chapter_id');
            $table->dropColumn('syllabus_verification_status');
        });
        Schema::dropIfExists('syllabus_exclusions');
        Schema::dropIfExists('syllabus_chapters');
        Schema::dropIfExists('syllabus_editions');
    }
};
