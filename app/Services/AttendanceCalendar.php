<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Schema;

class AttendanceCalendar
{
    public const LABELS = [
        'H' => 'Hadir', 'T' => 'Terlambat', 'A' => 'Alfa', 'C' => 'Cuti',
        'S' => 'Sakit', 'I' => 'Izin', 'K' => 'Cuti khusus', 'L' => 'Libur', 'B' => 'Belum absen', '' => '',
    ];

    private static ?bool $hasReview = null;
    private static ?bool $hasLeave = null;

    public function month(Employee $e, Carbon $month): array
    {
        $from  = $month->copy()->startOfMonth()->startOfDay();
        $to    = $month->copy()->endOfMonth()->startOfDay();
        $today = today();
        $fs    = $from->toDateString();
        $ts    = $to->toDateString();
        $workDays = config('attendance.leave.work_days', [1, 2, 3, 4, 5]);

        $hasReview = self::$hasReview ??= Schema::hasColumn('attendances', 'review_status');
        $hasLeave  = self::$hasLeave ??= Schema::hasTable('leave_requests');

        $atts = Attendance::with(['shift', 'inLocation'])
            ->where('employee_id', $e->id)
            ->whereBetween('date', [$fs, $ts])
            ->where(fn ($q) => $q->whereNotNull('check_in_at')->orWhereNotNull('check_out_at'))
            ->get()->keyBy(fn ($a) => $a->date->toDateString());

        $holidays = Holiday::whereBetween('date', [$fs, $ts])->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all();

        // cuti, izin, dan sakit yang sudah disetujui, dipetakan per tanggal
        $leaveMap = [];
        if ($hasLeave) {
            LeaveRequest::with('type')
                ->where('employee_id', $e->id)->where('status', 'approved')
                ->where('start_date', '<=', $ts)->where('end_date', '>=', $fs)->get()
                ->each(function ($l) use (&$leaveMap, $from, $to) {
                    $code = match ($l->type->code) {
                        'CT' => 'C', 'SKT' => 'S', 'IZN' => 'I', default => 'K',
                    };
                    $s  = $l->start_date->gt($from) ? $l->start_date : $from;
                    $en = $l->end_date->lt($to) ? $l->end_date : $to;

                    foreach (CarbonPeriod::create($s, $en) as $d) {
                        $leaveMap[$d->toDateString()] = [
                            'code' => $code, 'name' => $l->type->name,
                            'half' => (bool) $l->is_half_day, 'reason' => $l->reason,
                        ];
                    }
                });
        }

        $days = [];
        $sum  = [
            'hari_kerja' => 0, 'hadir' => 0, 'hadir_kerja' => 0, 'tepat' => 0, 'terlambat' => 0,
            'late_min' => 0, 'early_min' => 0, 'work_min' => 0, 'alfa' => 0, 'leave' => 0, 'libur' => 0,
            'pct' => null, 'avg_in' => null, 'avg_work' => 0,
        ];
        $inTotal = $inCount = $workCount = 0;

        foreach (CarbonPeriod::create($from, $to) as $d) {
            $ds     = $d->toDateString();
            $att    = $atts->get($ds);
            $isOff  = ! in_array($d->dayOfWeekIso, $workDays, true) || isset($holidays[$ds]);
            $before = $e->join_date && $d->lt($e->join_date);

            $rejected = $hasReview && $att && $att->review_status === 'rejected';
            $valid    = $att && ! $rejected;

            // absen yang tercatat SELALU dihitung hadir, apa pun tanggal masuk karyawan
            if ($valid) {
                $code = $att->status === 'terlambat' ? 'T' : 'H';
            } elseif ($before) {
                $code = '';
            } elseif ($isOff) {
                $code = 'L';
            } elseif (isset($leaveMap[$ds])) {
                $code = $leaveMap[$ds]['code'];
            } elseif ($d->gt($today)) {
                $code = '';
            } elseif ($d->isSameDay($today)) {
                $code = 'B';
            } else {
                $code = 'A';
            }

            $open = $att && $att->check_in_at && ! $att->check_out_at;

            $days[$ds] = [
                'date'      => $d->copy(),
                'code'      => $code,
                'att'       => $att,
                'rejected'  => (bool) $rejected,
                'holiday'   => $holidays[$ds] ?? null,
                'leave'     => $leaveMap[$ds] ?? null,
                'no_out'    => $valid && $open && $d->lt($today),
                'working'   => $valid && $open && $d->isSameDay($today),
                'corrected' => $att && array_key_exists('is_corrected', $att->getAttributes()) && $att->is_corrected,
            ];

            if (($valid || ! $before) && ! $isOff && $d->lte($today)) {
                $sum['hari_kerja']++;
            }

            switch ($code) {
                case 'H':
                case 'T':
                    $sum['hadir']++;
                    if (! $isOff) $sum['hadir_kerja']++;
                    $code === 'T' ? $sum['terlambat']++ : $sum['tepat']++;
                    $sum['late_min']  += (int) $att->late_minutes;
                    $sum['early_min'] += (int) $att->early_leave_minutes;
                    $sum['work_min']  += (int) $att->work_minutes;
                    if ($att->check_in_at) {
                        $inTotal += $att->check_in_at->hour * 60 + $att->check_in_at->minute;
                        $inCount++;
                    }
                    if ($att->work_minutes > 0) {
                        $workCount++;
                    }
                    break;
                case 'A': $sum['alfa']++; break;
                case 'C': case 'S': case 'I': case 'K': $sum['leave']++; break;
                case 'L': $sum['libur']++; break;
            }
        }

        $eligible = $sum['hadir_kerja'] + $sum['alfa'];
        $sum['pct'] = $eligible > 0 ? round($sum['hadir_kerja'] / $eligible * 100, 1) : null;

        if ($inCount > 0) {
            $avg = intdiv($inTotal, $inCount);
            $sum['avg_in'] = sprintf('%02d:%02d', intdiv($avg, 60), $avg % 60);
        }
        $sum['avg_work'] = $workCount > 0 ? intdiv($sum['work_min'], $workCount) : 0;

        // susunan minggu: Senin sampai Minggu
        $cells = array_fill(0, $from->dayOfWeekIso - 1, null);
        foreach (array_keys($days) as $ds) {
            $cells[] = $ds;
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return [
            'month'   => $from,
            'days'    => $days,
            'weeks'   => array_chunk($cells, 7),
            'summary' => $sum,
            'alfa'    => collect($days)->filter(fn ($x) => $x['code'] === 'A')->keys()->sortDesc()->values()->all(),
        ];
    }
}