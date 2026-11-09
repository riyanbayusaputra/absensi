<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();

            $t->dateTime('check_in_at')->nullable();
            $t->dateTime('check_out_at')->nullable();

            // masuk
            $t->foreignId('in_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $t->decimal('in_lat', 10, 7)->nullable();
            $t->decimal('in_lng', 10, 7)->nullable();
            $t->float('in_accuracy')->nullable();
            $t->unsignedInteger('in_distance_m')->nullable();
            $t->string('in_photo')->nullable();
            $t->decimal('in_face_score', 6, 4)->nullable();

            // pulang
            $t->foreignId('out_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $t->decimal('out_lat', 10, 7)->nullable();
            $t->decimal('out_lng', 10, 7)->nullable();
            $t->float('out_accuracy')->nullable();
            $t->unsignedInteger('out_distance_m')->nullable();
            $t->string('out_photo')->nullable();
            $t->decimal('out_face_score', 6, 4)->nullable();

            $t->string('status')->default('tepat_waktu');     // tepat_waktu | terlambat
            $t->unsignedInteger('late_minutes')->default(0);
            $t->unsignedInteger('early_leave_minutes')->default(0);
            $t->unsignedInteger('work_minutes')->default(0);

            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();

            $t->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};