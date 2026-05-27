<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('allowed_grades')->nullable();
            $table->json('allowed_subjects')->nullable();
            $table->json('allowed_categories')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 500);
            $table->json('config_snapshot');
            $table->json('layout_snapshot')->nullable();
            $table->enum('status', ['draft', 'saved', 'archived'])->default('saved');
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 100);
            $table->json('meta')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->boolean('success');
            $table->timestamp('logged_in_at');
            $table->timestamp('logged_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('saved_papers');
        Schema::dropIfExists('teacher_permissions');
    }
};

