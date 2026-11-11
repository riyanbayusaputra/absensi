<?php

namespace App\Livewire\Employee\Leave;

use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.mobile')]
class Create extends Component
{
    use WithFileUploads;

    public $leave_type_id = '';
    public string $start_date = '';
    public string $end_date = '';
    public bool $is_half_day = false;
    public string $half_day_session = 'pagi';
    public string $reason = '';
    public $attachment = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->employee, 403, 'Akun belum terhubung dengan data karyawan.');
        $this->start_date = $this->end_date = today()->toDateString();
    }

    public function updatedStartDate(): void
    {
        if ($this->end_date < $this->start_date || $this->is_half_day) {
            $this->end_date = $this->start_date;
        }
    }

    public function updatedIsHalfDay(): void
    {
        if ($this->is_half_day) {
            $this->end_date = $this->start_date;
        }
    }

    public function updatedLeaveTypeId(): void
    {
        if (! $this->type?->allow_half_day) {
            $this->is_half_day = false;
        }
    }

    #[Computed]
    public function types()
    {
        return LeaveType::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function type(): ?LeaveType
    {
        return $this->leave_type_id
            ? $this->types->firstWhere('id', (int) $this->leave_type_id)
            : null;
    }

    /** Pratinjau durasi dan saldo, diperbarui langsung saat form berubah. */
    #[Computed]
    public function preview(): ?array
    {
        if (! $this->type || ! $this->start_date || ! $this->end_date) return null;

        try {
            $s = Carbon::parse($this->start_date)->startOfDay();
            $e = Carbon::parse($this->end_date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
        if ($e->lt($s) || $s->year < now()->year - 1 || $s->year > now()->year + 1) return null;

        $svc      = app(LeaveService::class);
        $employee = auth()->user()->employee;
        $svc->ensureBalances($employee, $s->year);

        $wd   = $svc->workingDays($s, $e, $employee);
        $days = ($this->is_half_day && $this->type->allow_half_day) ? 0.5 : $wd;

        return ['days' => $days, 'available' => $svc->available($employee, $this->type, $s->year)];
    }

    public function submit(LeaveService $svc)
    {
        $this->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|min:5|max:500',
            'attachment'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ], [
            'leave_type_id.required'  => 'Pilih jenis cuti/izin.',
            'start_date.required'     => 'Tanggal mulai wajib diisi.',
            'end_date.required'       => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'reason.required'         => 'Alasan wajib diisi.',
            'reason.min'              => 'Alasan terlalu singkat (minimal 5 karakter).',
            'attachment.mimes'        => 'Lampiran harus berupa JPG, PNG, atau PDF.',
            'attachment.max'          => 'Ukuran lampiran maksimal 2 MB.',
        ]);

        $svc->submit(auth()->user()->employee, [
            'leave_type_id'    => $this->leave_type_id,
            'start_date'       => $this->start_date,
            'end_date'         => $this->end_date,
            'is_half_day'      => $this->is_half_day,
            'half_day_session' => $this->half_day_session,
            'reason'           => $this->reason,
        ], $this->attachment);

        session()->flash('status', 'Pengajuan terkirim dan menunggu persetujuan.');

        return $this->redirectRoute('app.leave', navigate: true);
    }

    public function render()
    {
        return view('livewire.employee.leave.create');
    }
}