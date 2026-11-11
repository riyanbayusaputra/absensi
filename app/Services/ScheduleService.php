<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Closure;

class ScheduleService
{
    /**
     * Pengecek libur untuk banyak karyawan sekaligus (hanya 2 query).
     * Urutan: libur nasional -> jadwal karyawan pada tanggal itu -> pola standar (config work_days).
     */
    public function resolver(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Closure
    {
        $fs = $from->toDateString();
        $ts = $to->toDateString();

        $holidays = Holiday::whereBetween('date', [$fs, $ts])->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => true])->all();

        $rows = WorkSchedule::whereIn('employee_id', $employeeIds)->whereBetween('date', [$fs, $ts])->get()
            ->groupBy('employee_id')
            ->map(fn ($g) => $g->mapWithKeys(fn ($r) => [$r->date->toDateString() => (bool) $r->is_off])->all())
            ->all();

        $workDays = config('attendance.leave.work_days', [1, 2, 3, 4, 5]);

        return function (int $employeeId, CarbonInterface $date) use ($holidays, $rows, $workDays): bool {
            $ds = $date->toDateString();

            if (isset($holidays[$ds])) return true;
            if (isset($rows[$employeeId][$ds])) return $rows[$employeeId][$ds];

            return ! in_array($date->dayOfWeekIso, $workDays, true);
        };
    }

    public function isOff(Employee $e, CarbonInterface $date): bool
    {
        $isOff = $this->resolver([$e->id], $date, $date);

        return $isOff($e->id, $date);
    }

    /** Daftar hari untuk satu karyawan (halaman Jadwal Saya). */
    public function forEmployee(Employee $e, CarbonInterface $from, CarbonInterface $to): array
    {
        $isOff = $this->resolver([$e->id], $from, $to);
        $fs = $from->toDateString();
        $ts = $to->toDateString();

        $holidays = Holiday::whereBetween('date', [$fs, $ts])->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all();

        $explicit = WorkSchedule::where('employee_id', $e->id)->whereBetween('date', [$fs, $ts])->get()
            ->mapWithKeys(fn ($r) => [$r->date->toDateString() => true])->all();

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $ds = $d->toDateString();
            $days[$ds] = [
                'date'     => $d->copy(),
                'off'      => $isOff($e->id, $d),
                'holiday'  => $holidays[$ds] ?? null,
                'explicit' => isset($explicit[$ds]),
            ];
        }

        return $days;
    }
}