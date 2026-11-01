<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_permissions', function (Blueprint $table) {
            foreach (['can_generate_paper', 'can_edit_paper', 'can_delete_paper', 'can_add_question', 'can_edit_question', 'can_delete_question'] as $field) {
                $table->boolean($field)->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_permissions', fn (Blueprint $table) => $table->dropColumn(['can_generate_paper', 'can_edit_paper', 'can_delete_paper', 'can_add_question', 'can_edit_question', 'can_delete_question']));
    }
};
