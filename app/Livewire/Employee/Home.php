<?php

namespace App\Livewire\Employee;

use App\Services\AttendanceCalendar;
use App\Services\AttendanceService;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile')]
class Home extends Component
{
    public function markRead(): void
    {
        if (Schema::hasTable('notifications')) {
            auth()->user()->unreadNotifications->markAsRead();
        }
    }

    public function render(AttendanceService $svc, AttendanceCalendar $calendar, ScheduleService $schedule)
    {
        $user     = auth()->user();
        $employee = $user->employee?->load('shift', 'position', 'department');

        $notifs = collect();
        $unread = 0;
        if (Schema::hasTable('notifications')) {
            $unread = $user->unreadNotifications()->count();
            $notifs = $user->unreadNotifications()->latest()->limit(5)->get();
        }

        $annual = null;
        if ($employee && class_exists(\App\Models\LeaveBalance::class) && Schema::hasTable('leave_balances')) {
            $annual = \App\Models\LeaveBalance::whereHas('type', fn ($q) => $q->where('code', 'CT'))
                ->where('employee_id', $employee->id)->where('year', now()->year)->first();
        }

        // jadwal minggu ini (Senin sampai Minggu)
        $week = [];
        $weekConfigured = false;
        $todayOff = false;
        $todayHoliday = null;

        if ($employee) {
            $mon  = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $week = $schedule->forEmployee($employee, $mon, $mon->copy()->addDays(6));

            $weekConfigured = collect($week)->contains('explicit', true);

            $t = $week[today()->toDateString()] ?? null;
            $todayOff     = (bool) ($t['off'] ?? false);
            $todayHoliday = $t['holiday'] ?? null;
        }

        return view('livewire.employee.home', [
            'user'           => $user,
            'employee'       => $employee,
            'next'           => $employee ? $svc->nextAction($employee) : null,
            'summary'        => $employee ? $calendar->month($employee, today())['summary'] : null,
            'notifs'         => $notifs,
            'unread'         => $unread,
            'annual'         => $annual,
            'week'           => $week,
            'weekConfigured' => $weekConfigured,
            'todayOff'       => $todayOff,
            'todayHoliday'   => $todayHoliday,
            'serverMs'       => now()->getTimestamp() * 1000,
            'tz'             => config('app.timezone'),
            'tzLabel'        => now()->format('T'),
        ]);
    }
}