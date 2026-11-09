<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        // HRD: semua permission, kecuali pengelolaan role
        Role::findByName('hrd')->syncPermissions(
            Permission::where('name', 'not like', '%Role%')->pluck('name')->all()
        );

        // Atasan: hanya melihat karyawan (otomatis hanya timnya)
        Role::findByName('atasan')->syncPermissions(
            Permission::whereIn('name', ['ViewAny:Employee', 'View:Employee'])->pluck('name')->all()
        );
        // Atasan: melihat karyawan, absensi, dan pengajuan cuti timnya (otomatis dibatasi ke timnya)
Role::findByName('atasan')->syncPermissions(
    Permission::whereIn('name', [
        'ViewAny:Employee',     'View:Employee',
        'ViewAny:Attendance',   'View:Attendance',
        'ViewAny:LeaveRequest', 'View:LeaveRequest',
    ])->pluck('name')->all()
);
    }
}