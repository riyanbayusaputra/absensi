<?php

namespace App\Livewire\Employee;

use App\Services\ScheduleService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile')]
class Schedule extends Component
{
    public string $week = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->employee, 403, 'Akun belum terhubung dengan data karyawan.');

        $this->week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    private function monday(): Carbon
    {
        $base = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $min  = $base->copy()->subWeeks(4);
        $max  = $base->copy()->addWeeks(8);

        try {
            $m = Carbon::parse($this->week)->startOfWeek(Carbon::MONDAY)->startOfDay();
        } catch (\Throwable) {
            return $base;
        }

        return $m->lt($min) ? $min : ($m->gt($max) ? $max : $m);
    }

    public function prevWeek(): void
    {
        $this->week = $this->monday()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = $this->monday()->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function render(ScheduleService $svc)
    {
        $employee = auth()->user()->employee->load('shift');
        $mon      = $this->monday();
        $days     = $svc->forEmployee($employee, $mon, $mon->copy()->addDays(6));

        return view('livewire.employee.schedule', [
            'employee'   => $employee,
            'mon'        => $mon,
            'days'       => $days,
            'workCount'  => collect($days)->where('off', false)->count(),
            'configured' => collect($days)->contains('explicit', true),
            'isThisWeek' => $mon->isSameDay(now()->startOfWeek(Carbon::MONDAY)),
        ]);
    }
}