<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->unsignedInteger('assistant_request_limit')->default(20);
            $table->unsignedInteger('assistant_daily_limit')->default(10);
            $table->timestamp('assistant_budget_started_at')->nullable();
        });
        Schema::table('teacher_permissions', fn (Blueprint $table) => $table->boolean('can_use_assistant')->default(true));
        Schema::create('assistant_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_import_id')->nullable()->constrained('ai_imports')->nullOnDelete();
            $table->uuid('request_key');
            $table->string('mode', 16);
            $table->string('status', 16)->default('reserved');
            $table->timestamp('reserved_at');
            $table->timestamp('provider_started_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'request_key']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'provider_started_at']);
        });
        Schema::create('assistant_budget_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administrator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('action');
            $table->json('previous_values');
            $table->json('new_values');
            $table->text('note');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('assistant_budget_events');
        Schema::dropIfExists('assistant_requests');
        Schema::table('teacher_permissions', fn (Blueprint $table) => $table->dropColumn('can_use_assistant'));
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn(['assistant_request_limit', 'assistant_daily_limit', 'assistant_budget_started_at']));
    }
};
