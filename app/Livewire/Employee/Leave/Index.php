<?php

namespace App\Livewire\Employee\Leave;

use App\Exceptions\LeaveException;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile')]
class Index extends Component
{
    public function cancel(int $id, LeaveService $svc): void
    {
        $r = LeaveRequest::where('employee_id', auth()->user()->employee->id)->findOrFail($id);

        try {
            $svc->cancel($r, auth()->user());
            session()->flash('status', 'Pengajuan dibatalkan.');
        } catch (LeaveException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(LeaveService $svc)
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403, 'Akun belum terhubung dengan data karyawan.');

        $year = now()->year;
        $svc->ensureBalances($employee, $year);

        $pending = LeaveRequest::where('employee_id', $employee->id)->where('status', 'pending')
            ->whereYear('start_date', $year)->selectRaw('leave_type_id, SUM(days) as d')
            ->groupBy('leave_type_id')->pluck('d', 'leave_type_id');

        $balances = LeaveBalance::with('type')->where('employee_id', $employee->id)->where('year', $year)->get()
            ->map(fn ($b) => [
                'name'      => $b->type->name,
                'available' => $b->remaining - (float) ($pending[$b->leave_type_id] ?? 0),
                'used'      => $b->used,
                'total'     => $b->quota + $b->carried_over,
                'pending'   => (float) ($pending[$b->leave_type_id] ?? 0),
            ]);

        $requests = LeaveRequest::with(['type', 'approvals.approver'])
            ->where('employee_id', $employee->id)->latest('id')->limit(30)->get();

        return view('livewire.employee.leave.index', compact('year', 'balances', 'requests'));
    }
}