<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Support\TeamScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;
use App\Services\ScheduleService;

class AttendanceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'hrd', 'atasan']) ?? false;
    }

        protected function getStats(): array
    {
        $user  = auth()->user();
        $today = today();

        $ids   = TeamScope::restrict(Employee::where('status', 'aktif'), $user, null)->pluck('id')->all();
        $total = count($ids);

        // karyawan yang dijadwalkan masuk hari ini (libur pribadi dan libur nasional dikecualikan)
        $isOff     = app(ScheduleService::class)->resolver($ids, $today, $today);
        $scheduled = collect($ids)->reject(fn ($id) => $isOff($id, $today))->values();

        $att        = fn () => TeamScope::restrict(Attendance::whereDate('date', $today), $user);
        $hadir      = $att()->count();
        $telat      = $att()->where('status', 'terlambat')->count();
        $presentIds = $att()->pluck('employee_id')->unique();

        $onLeaveIds = collect();
        $pending    = 0;
        if (Schema::hasTable('leave_requests')) {
            $onLeaveIds = TeamScope::restrict(
                LeaveRequest::where('status', 'approved')->where('is_half_day', false)
                    ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today),
                $user
            )->pluck('employee_id')->unique();

            $pending = TeamScope::restrict(LeaveRequest::where('status', 'pending'), $user)->count();
        }

        $belum = $scheduled->diff($presentIds)->diff($onLeaveIds)->count();
        $libur = $total - $scheduled->count();

        return [
            Stat::make('Hadir hari ini', $hadir)
                ->description("dari {$total} karyawan aktif")
                ->descriptionIcon('heroicon-o-check-circle')->color('success'),
            Stat::make('Terlambat', $telat)
                ->description('hari ini')
                ->descriptionIcon('heroicon-o-clock')->color($telat > 0 ? 'warning' : 'gray'),
            Stat::make('Belum absen', $belum)
                ->description('dari ' . $scheduled->count() . ' yang dijadwalkan masuk')
                ->descriptionIcon('heroicon-o-user-minus')->color($belum > 0 ? 'danger' : 'gray'),
            Stat::make('Libur hari ini', $libur)
                ->description('sesuai jadwal / libur nasional')
                ->descriptionIcon('heroicon-o-moon')->color('gray'),
            Stat::make('Cuti / izin / sakit', $onLeaveIds->count())
                ->description("{$pending} pengajuan menunggu")
                ->descriptionIcon('heroicon-o-calendar-days')->color('info'),
        ];
    }
}