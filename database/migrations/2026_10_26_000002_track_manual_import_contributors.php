<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contributor_board_id')->nullable()->constrained('boards')->nullOnDelete();
            $table->dateTime('contributed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('contributor_board_id');
            $table->dropColumn('contributed_at');
        });
    }
};
