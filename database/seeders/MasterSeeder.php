<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\OfficeLocation;
use App\Models\Position;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['HRD', 'Keuangan', 'IT', 'Operasional'] as $d) {
            Department::firstOrCreate(['name' => $d]);
        }
        foreach (['Staff', 'Supervisor', 'Manager'] as $p) {
            Position::firstOrCreate(['name' => $p]);
        }

        Shift::firstOrCreate(['name' => 'Reguler'], [
            'start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_min' => 10,
        ]);
        Shift::firstOrCreate(['name' => 'Malam'], [
            'start_time' => '22:00', 'end_time' => '06:00', 'late_tolerance_min' => 10, 'is_overnight' => true,
        ]);

        OfficeLocation::firstOrCreate(['name' => 'Kantor Pusat'], [
            'latitude' => -6.2000000, 'longitude' => 106.8166660, 'radius_m' => 100,
        ]);
    }
}