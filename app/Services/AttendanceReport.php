<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\TeamScope;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AttendanceReport
{
    public const LABELS = [
        'H' => 'Hadir', 'T' => 'Hadir terlambat', 'A' => 'Alfa', 'C' => 'Cuti',
        'S' => 'Sakit', 'I' => 'Izin', 'K' => 'Cuti khusus', 'L' => 'Libur', 'B' => 'Belum absen',
    ];

    /** [latar, teks] */
    public const COLORS = [
        'H' => ['#dcfce7', '#166534'], 'T' => ['#fef3c7', '#92400e'], 'A' => ['#fee2e2', '#991b1b'],
        'C' => ['#dbeafe', '#1e40af'], 'S' => ['#f3e8ff', '#6b21a8'], 'I' => ['#ccfbf1', '#115e59'],
        'K' => ['#e0e7ff', '#3730a3'], 'L' => ['#f3f4f6', '#6b7280'], 'B' => ['#fef9c3', '#854d0e'],
    ];

    public function build(User $viewer, Carbon $from, Carbon $to, ?int $departmentId = null): array
    {
        $from = $from->copy()->startOfDay();
        $to   = $to->copy()->startOfDay();

        $employees = TeamScope::restrict(
            Employee::query()->with('department')
                ->where(fn ($q) => $q->whereNull('join_date')->orWhere('join_date', '<=', $to->toDateString()))
                ->where(fn ($q) => $q->where('status', 'aktif')
                    ->orWhereHas('attendances', fn ($a) => $a->whereBetween('date', [$from->toDateString(), $to->toDateString()])))
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->orderBy('name'),
            $viewer,
            null
        )->get();

        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $dates[] = $d->copy();
        }

        $rows = $this->rowsFor($employees, $from, $to);

        return ['from' => $from, 'to' => $to, 'dates' => $dates, 'rows' => $rows, 'departments' => $this->departments($rows)];
    }

    public function rowsFor(Collection $employees, Carbon $from, Carbon $to): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $today = today();
        $fs    = $from->toDateString();
        $ts    = $to->toDateString();
        $ids   = $employees->pluck('id')->all();
        $isOffFor  = app(ScheduleService::class)->resolver($ids, $from, $to);
        $hasReview = Schema::hasColumn('attendances', 'review_status');
        $hasLeave  = Schema::hasTable('leave_requests');

        // semua absen yang tercatat dihitung hadir (yang ditandai tidak valid diabaikan bila fitur itu ada)
        $atts = Attendance::whereIn('employee_id', $ids)->whereBetween('date', [$fs, $ts])
            ->when($hasReview, fn ($q) => $q->where('review_status', '!=', 'rejected'))
            ->where(fn ($q) => $q->whereNotNull('check_in_at')->orWhereNotNull('check_out_at'))
            ->get()->groupBy('employee_id')
            ->map(fn ($g) => $g->keyBy(fn ($a) => $a->date->toDateString()));

        $holidays = Holiday::whereBetween('date', [$fs, $ts])->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all();

        $leaveMap = [];
        if ($hasLeave) {
            LeaveRequest::with('type')->whereIn('employee_id', $ids)->where('status', 'approved')
                ->where('start_date', '<=', $ts)->where('end_date', '>=', $fs)->get()
                ->each(function ($l) use (&$leaveMap, $from, $to) {
                    $code = match ($l->type->code) { 'CT' => 'C', 'SKT' => 'S', 'IZN' => 'I', default => 'K' };
                    $s  = $l->start_date->gt($from) ? $l->start_date : $from;
                    $en = $l->end_date->lt($to) ? $l->end_date : $to;
                    foreach (CarbonPeriod::create($s, $en) as $d) {
                        $leaveMap[$l->employee_id][$d->toDateString()] = $code;
                    }
                });
        }

        $rows = [];

        foreach ($employees as $e) {
            $cells = [];
            $sum = [
                'hari_kerja' => 0, 'hadir' => 0, 'hadir_kerja' => 0, 'tepat' => 0, 'terlambat' => 0,
                'late_min' => 0, 'early_min' => 0, 'work_min' => 0,
                'alfa' => 0, 'cuti' => 0, 'sakit' => 0, 'izin' => 0, 'khusus' => 0, 'libur' => 0, 'pct' => null,
            ];

            foreach (CarbonPeriod::create($from, $to) as $d) {
                $ds     = $d->toDateString();
                $isOff  = $isOffFor($e->id, $d);
                $att    = $atts->get($e->id)?->get($ds);
                $before = $e->join_date && $d->lt($e->join_date);

                if ($att) {
                    $code = $att->status === 'terlambat' ? 'T' : 'H';
                } elseif ($before) {
                    $code = '';
                } elseif ($isOff) {
                    $code = 'L';
                } elseif (isset($leaveMap[$e->id][$ds])) {
                    $code = $leaveMap[$e->id][$ds];
                } elseif ($d->gt($today)) {
                    $code = '';
                } elseif ($d->isSameDay($today)) {
                    $code = 'B';
                } else {
                    $code = 'A';
                }

                $cells[$ds] = $code;

                if (($att || ! $before) && ! $isOff && $d->lte($today)) {
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
                        break;
                    case 'A': $sum['alfa']++; break;
                    case 'C': $sum['cuti']++; break;
                    case 'S': $sum['sakit']++; break;
                    case 'I': $sum['izin']++; break;
                    case 'K': $sum['khusus']++; break;
                    case 'L': $sum['libur']++; break;
                }
            }

            $eligible = $sum['hadir_kerja'] + $sum['alfa'];
            $sum['pct'] = $eligible > 0 ? round($sum['hadir_kerja'] / $eligible * 100, 1) : null;

            $rows[] = ['employee' => $e, 'cells' => $cells, 'sum' => $sum];
        }

        return $rows;
    }

    public function departments(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn ($r) => $r['employee']->department?->name ?? 'Tanpa departemen')
            ->map(function ($g, $name) {
                $s = fn (string $k) => $g->sum(fn ($r) => $r['sum'][$k]);
                $eligible = $s('hadir_kerja') + $s('alfa');

                return [
                    'name' => $name, 'employees' => $g->count(), 'hadir' => $s('hadir'),
                    'terlambat' => $s('terlambat'), 'late_min' => $s('late_min'), 'alfa' => $s('alfa'),
                    'leave' => $s('cuti') + $s('sakit') + $s('izin') + $s('khusus'),
                    'pct' => $eligible > 0 ? round($s('hadir_kerja') / $eligible * 100, 1) : null,
                ];
            })
            ->sortBy('name')->values()->all();
    }
}