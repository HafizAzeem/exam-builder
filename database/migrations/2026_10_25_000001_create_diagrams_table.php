<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagrams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('builtin_key')->nullable()->unique();
            $table->string('title');
            $table->string('category', 80)->index();
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->string('status', 20)->default('private')->index();
            $table->boolean('is_builtin')->default(false);
            $table->json('elements')->nullable();
            $table->string('image_path')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagrams');
    }
};
