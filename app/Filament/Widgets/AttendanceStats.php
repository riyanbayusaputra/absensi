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

        $total = TeamScope::restrict(Employee::where('status', 'aktif'), $user, null)->count();

        $att = fn () => TeamScope::restrict(Attendance::whereDate('date', $today), $user);
        $hadir = $att()->count();
        $telat = $att()->where('status', 'terlambat')->count();

        $cuti = 0;
        $pending = 0;
        if (Schema::hasTable('leave_requests')) {
            $cuti = TeamScope::restrict(
                LeaveRequest::where('status', 'approved')->where('is_half_day', false)
                    ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today),
                $user
            )->pluck('employee_id')->unique()->count();

            $pending = TeamScope::restrict(LeaveRequest::where('status', 'pending'), $user)->count();
        }

        $isWorkDay = in_array($today->dayOfWeekIso, config('attendance.leave.work_days', [1, 2, 3, 4, 5]), true)
            && ! Holiday::whereDate('date', $today)->exists();
        $belum = $isWorkDay ? max(0, $total - $hadir - $cuti) : 0;

        return [
            Stat::make('Hadir hari ini', $hadir)
                ->description("dari {$total} karyawan aktif")
                ->descriptionIcon('heroicon-o-check-circle')->color('success'),
            Stat::make('Terlambat', $telat)
                ->description('hari ini')
                ->descriptionIcon('heroicon-o-clock')->color($telat > 0 ? 'warning' : 'gray'),
            Stat::make('Belum absen', $belum)
                ->description($isWorkDay ? 'di luar yang cuti/izin' : 'hari ini bukan hari kerja')
                ->descriptionIcon('heroicon-o-user-minus')->color($belum > 0 ? 'danger' : 'gray'),
            Stat::make('Cuti / izin / sakit', $cuti)
                ->description("{$pending} pengajuan menunggu")
                ->descriptionIcon('heroicon-o-calendar-days')->color('info'),
        ];
    }
}