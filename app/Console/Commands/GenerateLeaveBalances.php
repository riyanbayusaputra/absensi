<?php

namespace App\Console\Commands;

use App\Services\LeaveService;
use Illuminate\Console\Command;

class GenerateLeaveBalances extends Command
{
    protected $signature = 'leave:generate-balances {year?}';
    protected $description = 'Buat saldo cuti tahunan (dan carry-over) untuk karyawan aktif';

    public function handle(LeaveService $svc): int
    {
        $year = (int) ($this->argument('year') ?: now()->year);
        $n = $svc->generateForAll($year);

        $this->info("Saldo {$year} diproses untuk {$n} karyawan.");

        return self::SUCCESS;
    }
}