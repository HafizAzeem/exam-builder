<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('model_name', 200)->default('gemini-2.5-flash')->change();
            $table->string('openrouter_model', 200)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('model_name', 100)->default('gemini-2.5-flash')->change();
            $table->string('openrouter_model', 150)->nullable()->change();
        });
    }
};
