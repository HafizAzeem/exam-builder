<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_imports', function (Blueprint $table) {
            $table->string('mode', 20)->default('upload')->after('user_id');
            $table->json('meta')->nullable()->after('error_message');
        });

        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->foreignId('imported_question_id')
                ->nullable()
                ->after('duplicate_of_question_id')
                ->constrained('questions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('imported_question_id');
        });

        Schema::table('ai_imports', function (Blueprint $table) {
            $table->dropColumn(['mode', 'meta']);
        });
    }
};
