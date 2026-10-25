<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('diagram')->nullable();
            $table->json('worksheet')->nullable();
        });
        Schema::table('mcq_options', function (Blueprint $table) {
            $table->json('option_diagrams')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn(['diagram', 'worksheet']));
        Schema::table('mcq_options', fn (Blueprint $table) => $table->dropColumn('option_diagrams'));
    }
};
