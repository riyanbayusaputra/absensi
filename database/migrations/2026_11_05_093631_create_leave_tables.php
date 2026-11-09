<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 10)->unique();
            $t->decimal('annual_quota', 4, 1)->default(0);
            $t->boolean('deducts_balance')->default(true);
            $t->boolean('requires_attachment')->default(false);
            $t->unsignedSmallInteger('min_notice_days')->default(0);
            $t->unsignedSmallInteger('backdate_days')->default(0);
            $t->unsignedSmallInteger('max_days_per_request')->nullable();
            $t->boolean('allow_half_day')->default(false);
            $t->decimal('carry_over_max', 4, 1)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained();
            $t->unsignedSmallInteger('year');
            $t->decimal('quota', 4, 1)->default(0);
            $t->decimal('carried_over', 4, 1)->default(0);
            $t->decimal('used', 4, 1)->default(0);
            $t->timestamps();
            $t->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_balance_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_balance_id')->constrained()->cascadeOnDelete();
            $t->decimal('change', 4, 1);
            $t->string('reason');
            $t->nullableMorphs('reference');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained();
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_half_day')->default(false);
            $t->string('half_day_session', 10)->nullable();     // pagi | siang
            $t->decimal('days', 4, 1);
            $t->text('reason');
            $t->string('attachment')->nullable();
            $t->string('status', 12)->default('pending');       // pending | approved | rejected | cancelled
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'start_date', 'end_date']);
            $t->index('status');
        });

        // Generik: dipakai juga untuk lembur dan koreksi di Step 7
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->morphs('approvable');
            $t->unsignedTinyInteger('step');
            $t->string('role', 20);                              // atasan | hrd
            $t->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 10)->default('pending');        // pending | approved | rejected | skipped
            $t->string('note', 300)->nullable();
            $t->timestamp('acted_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balance_logs');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};