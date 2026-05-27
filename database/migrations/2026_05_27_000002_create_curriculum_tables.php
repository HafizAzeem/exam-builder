<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique(); // 1-12
            $table->string('label_en', 50);
            $table->string('label_ur', 50)->nullable();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ur')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('title_en', 500);
            $table->string('title_ur', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('grades');
    }
};

