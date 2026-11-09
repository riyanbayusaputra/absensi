<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'hrd', 'atasan', 'karyawan'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@absensi.test'],
            [
                'name'                 => 'Super Admin',
                'password'             => 'password',
                'is_active'            => true,
                'must_change_password' => false,
            ]
        );
        $admin->assignRole('super_admin');
    }
}