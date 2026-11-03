<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subscription_plan')->default('legacy');
            $table->dateTime('trial_started_at')->nullable();
            $table->dateTime('access_expires_at')->nullable()->index();
        });
        DB::table('institutions')->orderBy('id')->chunkById(200, function ($schools) {
            foreach ($schools as $school) {
                DB::table('institutions')->where('id', $school->id)->update([
                    'access_expires_at' => Carbon::parse($school->expiry_date)->endOfDay()->format('Y-m-d H:i:s'),
                ]);
            }
        });
        Schema::table('users', fn (Blueprint $table) => $table->dateTime('verification_due_at')->nullable());
        DB::table('users')->whereNull('email_verified_at')->update(['verification_due_at' => now()->addHour()]);
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contributor_board_id')->nullable()->constrained('boards')->nullOnDelete();
        });
        Schema::create('school_account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administrator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('action');
            $table->string('plan')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->dateTime('previous_expires_at')->nullable();
            $table->dateTime('new_expires_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_account_events');
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('contributor_board_id');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('verification_due_at'));
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_id');
            $table->dropIndex(['access_expires_at']);
            $table->dropColumn(['subscription_plan', 'trial_started_at', 'access_expires_at']);
        });
    }
};
