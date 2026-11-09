<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'CT',  'name' => 'Cuti Tahunan',    'annual_quota' => 12, 'deducts_balance' => true,
             'min_notice_days' => 3, 'allow_half_day' => true, 'carry_over_max' => 5],
            ['code' => 'SKT', 'name' => 'Sakit',           'deducts_balance' => false,
             'requires_attachment' => true, 'backdate_days' => 3],
            ['code' => 'IZN', 'name' => 'Izin',            'deducts_balance' => false,
             'allow_half_day' => true, 'backdate_days' => 1, 'max_days_per_request' => 3],
            ['code' => 'MNK', 'name' => 'Cuti Menikah',    'deducts_balance' => false,
             'min_notice_days' => 7, 'max_days_per_request' => 3, 'requires_attachment' => true],
            ['code' => 'DKA', 'name' => 'Cuti Duka',       'deducts_balance' => false,
             'backdate_days' => 2, 'max_days_per_request' => 2],
            ['code' => 'MLH', 'name' => 'Cuti Melahirkan', 'deducts_balance' => false,
             'max_days_per_request' => 90, 'requires_attachment' => true],
            ['code' => 'TGJ', 'name' => 'Cuti Tanpa Gaji', 'deducts_balance' => false,
             'min_notice_days' => 7],
        ];

        foreach ($types as $t) {
            LeaveType::updateOrCreate(['code' => $t['code']], $t);
        }
    }
}