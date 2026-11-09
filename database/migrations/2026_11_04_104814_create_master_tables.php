<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('departments', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->timestamps();
    });

    Schema::create('positions', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->timestamps();
    });

    Schema::create('office_locations', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->decimal('latitude', 10, 7);
        $t->decimal('longitude', 10, 7);
        $t->unsignedInteger('radius_m')->default(100);
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });

    Schema::create('shifts', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->time('start_time');
        $t->time('end_time');
        $t->unsignedSmallInteger('late_tolerance_min')->default(0);
        $t->boolean('is_overnight')->default(false);
        $t->timestamps();
    });

    Schema::create('employees', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $t->string('nik')->unique();
        $t->string('name');
        $t->string('phone')->nullable();
        $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
        $t->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
        $t->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
        $t->foreignId('default_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
        $t->date('join_date')->nullable();
        $t->enum('status', ['aktif', 'nonaktif', 'resign'])->default('aktif');
        $t->string('photo')->nullable();
        $t->timestamps();
    });

    Schema::create('employee_locations', function (Blueprint $t) {
        $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
        $t->foreignId('office_location_id')->constrained()->cascadeOnDelete();
        $t->primary(['employee_id', 'office_location_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_tables');
    }
};
