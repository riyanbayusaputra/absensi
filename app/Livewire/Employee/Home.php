<?php

namespace App\Livewire\Employee;

use App\Services\AttendanceCalendar;
use App\Services\AttendanceService;
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

    public function render(AttendanceService $svc, AttendanceCalendar $calendar)
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

        return view('livewire.employee.home', [
            'user'     => $user,
            'employee' => $employee,
            'next'     => $employee ? $svc->nextAction($employee) : null,
            'summary'  => $employee ? $calendar->month($employee, today())['summary'] : null,
            'notifs'   => $notifs,
            'unread'   => $unread,
            'annual'   => $annual,
            'serverMs' => now()->getTimestamp() * 1000,
            'tz'       => config('app.timezone'),
            'tzLabel'  => now()->format('T'),
        ]);
    }
}