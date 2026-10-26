<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discipline-domain tables. On databases where these already exist
     * (they were provisioned outside of migrations), each creation is
     * skipped so this migration records cleanly without touching data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('offense_types')) {
            Schema::create('offense_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->unique();
                $table->text('description')->nullable();
                $table->enum('severity', ['minor', 'major', 'critical'])->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('student_offenses')) {
            Schema::create('student_offenses', function (Blueprint $table) {
                $table->id();
                $table->string('reference_no', 40)->unique();
                $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
                $table->foreignId('offense_type_id')->constrained('offense_types')->restrictOnDelete();
                $table->dateTime('incident_at');
                $table->string('location', 255)->nullable();
                $table->text('description');
                $table->text('witnesses')->nullable();
                $table->enum('status', ['recorded', 'pending_review', 'under_investigation', 'resolved', 'closed', 'dismissed'])->default('recorded');
                $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'incident_at']);
                $table->index(['status', 'recorded_at']);
                $table->index('offense_type_id');
            });
        }

        if (! Schema::hasTable('offense_actions')) {
            Schema::create('offense_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offense_id')->constrained('student_offenses')->restrictOnDelete();
                $table->string('action_type', 100);
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(['offense_id', 'created_at']);
                $table->index('recorded_by');
            });
        }

        if (! Schema::hasTable('student_rfid')) {
            Schema::create('student_rfid', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
                $table->string('card_uid', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('offense_audit_logs')) {
            Schema::create('offense_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('offense_id')->nullable()->constrained('student_offenses')->nullOnDelete();
                $table->string('action', 50);
                $table->string('entity_type', 80)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['entity_type', 'entity_id']);
                $table->index(['offense_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offense_audit_logs');
        Schema::dropIfExists('student_rfid');
        Schema::dropIfExists('offense_actions');
        Schema::dropIfExists('student_offenses');
        Schema::dropIfExists('offense_types');
    }
};
