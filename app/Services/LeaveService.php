<?php

namespace App\Services;

use App\Exceptions\LeaveException;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /* ---------- Hitung hari ---------- */

    /** Hari kerja dalam rentang, tanpa akhir pekan (config) dan hari libur/cuti bersama. */
    public function workingDays(Carbon $start, Carbon $end): int
    {
        $holidays = Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')->map(fn ($d) => $d->toDateString())->all();
        $workDays = config('attendance.leave.work_days');

        $n = 0;
        foreach (CarbonPeriod::create($start, $end) as $d) {
            if (in_array($d->dayOfWeekIso, $workDays, true) && ! in_array($d->toDateString(), $holidays, true)) {
                $n++;
            }
        }
        return $n;
    }

    /* ---------- Saldo ---------- */

    public function ensureBalances(Employee $e, int $year): void
    {
        $types = LeaveType::where('is_active', true)
            ->where('deducts_balance', true)->where('annual_quota', '>', 0)->get();

        foreach ($types as $t) {
            LeaveBalance::firstOrCreate(
                ['employee_id' => $e->id, 'leave_type_id' => $t->id, 'year' => $year],
                ['quota' => $this->quotaFor($e, $t, $year)]
            );
        }
    }

    private function quotaFor(Employee $e, LeaveType $t, int $year): float
    {
        $q = (float) $t->annual_quota;

        if (config('attendance.leave.prorata_new_employee') && $e->join_date && $e->join_date->year === $year) {
            $months = 13 - $e->join_date->month;          // bulan masuk ikut dihitung
            $q = floor($q * $months / 12 * 2) / 2;        // dibulatkan ke bawah, kelipatan 0,5
        }
        return $q;
    }

    /** Sisa saldo dikurangi pengajuan pending. null = jenis ini tidak memotong saldo. */
    public function available(Employee $e, LeaveType $t, int $year): ?float
    {
        if (! $t->deducts_balance) return null;

        $b = LeaveBalance::where(['employee_id' => $e->id, 'leave_type_id' => $t->id, 'year' => $year])->first();
        if (! $b) return 0.0;

        $pending = (float) LeaveRequest::where('employee_id', $e->id)
            ->where('leave_type_id', $t->id)->where('status', 'pending')
            ->whereYear('start_date', $year)->sum('days');

        return $b->remaining - $pending;
    }

    public function adjust(LeaveBalance $balance, float $change, string $reason, User $by): void
    {
        DB::transaction(function () use ($balance, $change, $reason, $by) {
            $b = LeaveBalance::lockForUpdate()->findOrFail($balance->id);

            if ($b->remaining + $change < 0) {
                throw new LeaveException('Penyesuaian membuat saldo menjadi negatif.');
            }

            $b->update(['quota' => $b->quota + $change]);
            $b->logs()->create([
                'change'     => $change,
                'reason'     => 'Penyesuaian HRD: ' . $reason,
                'created_by' => $by->id,
            ]);
        });

        activity()->causedBy($by)->performedOn($balance)->log("Penyesuaian saldo cuti {$change}: {$reason}");
    }

    public function generateForAll(int $year): int
    {
        $count = 0;

        Employee::where('status', 'aktif')->chunkById(100, function ($employees) use ($year, &$count) {
            foreach ($employees as $e) {
                $this->ensureBalances($e, $year);
                $this->applyCarryOver($e, $year);
                $count++;
            }
        });

        return $count;
    }

    private function applyCarryOver(Employee $e, int $year): void
    {
        $types = LeaveType::where('is_active', true)->where('deducts_balance', true)
            ->where('carry_over_max', '>', 0)->get();

        foreach ($types as $t) {
            $prev = LeaveBalance::where(['employee_id' => $e->id, 'leave_type_id' => $t->id, 'year' => $year - 1])->first();
            $cur  = LeaveBalance::where(['employee_id' => $e->id, 'leave_type_id' => $t->id, 'year' => $year])->first();
            if (! $prev || ! $cur) continue;

            $carry = min(max(0, $prev->remaining), $t->carry_over_max);
            $diff  = $carry - $cur->carried_over;
            if (abs($diff) < 0.01) continue;

            $cur->update(['carried_over' => $carry]);
            $cur->logs()->create(['change' => $diff, 'reason' => "Carry-over dari {$prev->year}"]);
        }
    }

    /* ---------- Pengajuan ---------- */

    public function submit(Employee $e, array $d, ?UploadedFile $file): LeaveRequest
    {
        $type = LeaveType::where('is_active', true)->find($d['leave_type_id'])
            ?? $this->fail('leave_type_id', 'Jenis cuti tidak valid.');
        $start = Carbon::parse($d['start_date'])->startOfDay();
        $end   = Carbon::parse($d['end_date'])->startOfDay();
        $half  = (bool) ($d['is_half_day'] ?? false);
        $today = today();

        if ($e->status !== 'aktif') {
            $this->fail('leave_type_id', 'Akun Anda tidak aktif.');
        }
        if ($end->lt($start)) {
            $this->fail('end_date', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }
        if ($start->year !== $end->year) {
            $this->fail('end_date', 'Pengajuan tidak boleh melewati pergantian tahun. Pisahkan menjadi dua pengajuan.');
        }
        if ($half && (! $type->allow_half_day || ! $start->isSameDay($end))) {
            $this->fail('is_half_day', 'Setengah hari hanya untuk jenis tertentu dan satu tanggal saja.');
        }

        if ($start->lt($today->copy()->subDays($type->backdate_days))) {
            $this->fail('start_date', $type->backdate_days > 0
                ? "{$type->name} hanya boleh diajukan maksimal {$type->backdate_days} hari ke belakang."
                : 'Tanggal mulai tidak boleh di masa lalu.');
        }
        if ($start->gte($today) && $start->lt($today->copy()->addDays($type->min_notice_days))) {
            $this->fail('start_date', "{$type->name} diajukan minimal {$type->min_notice_days} hari sebelum tanggal mulai.");
        }

        $workingDays = $this->workingDays($start, $end);
        if ($workingDays === 0) {
            $this->fail('start_date', 'Rentang tanggal tidak berisi hari kerja (akhir pekan atau libur).');
        }
        $days = $half ? 0.5 : (float) $workingDays;

        if ($type->max_days_per_request && $days > $type->max_days_per_request) {
            $this->fail('end_date', "Maksimal {$type->max_days_per_request} hari per pengajuan untuk {$type->name}.");
        }
        if ($type->requires_attachment && ! $file) {
            $this->fail('attachment', "Lampiran wajib untuk {$type->name} (mis. surat dokter).");
        }

        $overlap = LeaveRequest::where('employee_id', $e->id)->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())->exists();
        if ($overlap) {
            $this->fail('start_date', 'Tanggal bentrok dengan pengajuan cuti/izin lain yang masih aktif.');
        }

        return DB::transaction(function () use ($e, $type, $start, $end, $half, $days, $d, $file) {
            if ($type->deducts_balance) {
                $this->ensureBalances($e, $start->year);

                // kunci baris saldo agar dua pengajuan serentak tidak melampaui kuota
                LeaveBalance::where(['employee_id' => $e->id, 'leave_type_id' => $type->id, 'year' => $start->year])
                    ->lockForUpdate()->first();

                $avail = $this->available($e, $type, $start->year);
                if ($avail !== null && $days > $avail) {
                    $this->fail('leave_type_id', "Saldo {$type->name} tidak cukup (tersedia " . ($avail + 0) . " hari, diminta " . ($days + 0) . ').');
                }
            }

            $r = LeaveRequest::create([
                'employee_id'      => $e->id,
                'leave_type_id'    => $type->id,
                'start_date'       => $start->toDateString(),
                'end_date'         => $end->toDateString(),
                'is_half_day'      => $half,
                'half_day_session' => $half ? ($d['half_day_session'] ?? 'pagi') : null,
                'days'             => $days,
                'reason'           => $d['reason'],
                'attachment'       => $file?->store("leave/{$e->id}", 'local'),
                'status'           => 'pending',
            ]);

            $this->createSteps($r, $e);
            $r->load('approvals', 'employee.manager.user', 'type');
            $this->notifyNext($r);

            return $r;
        });
    }

    private function createSteps(LeaveRequest $r, Employee $e): void
    {
        $steps = [];

        foreach (config('attendance.leave.approval_levels') as $role) {
            if ($role === 'atasan' && ! $e->manager?->user?->hasAnyRole(['atasan', 'hrd', 'super_admin'])) {
                continue;   // tanpa atasan yang valid, langkah dilewati
            }
            $steps[] = $role;
        }
        if (! $steps) {
            $steps = ['hrd'];
        }

        foreach ($steps as $i => $role) {
            $r->approvals()->create(['step' => $i + 1, 'role' => $role]);
        }
    }

    /* ---------- Persetujuan ---------- */

    public function canAct(LeaveRequest $r, User $u): bool
    {
        if ($r->status !== 'pending') return false;
        if ($u->employee?->id === $r->employee_id) return false;       // tidak boleh menyetujui diri sendiri

        $a = $r->currentApproval();
        if (! $a) return false;
        if ($u->hasRole('super_admin')) return true;

        return match ($a->role) {
            'atasan' => $u->hasRole('atasan') && $u->employee && $r->employee->manager_id === $u->employee->id,
            'hrd'    => $u->hasRole('hrd'),
            default  => false,
        };
    }

    public function approve(LeaveRequest $request, User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $by, $note) {
            $r = LeaveRequest::lockForUpdate()->with('approvals', 'employee.manager.user', 'type')->findOrFail($request->id);

            if (! $this->canAct($r, $by)) {
                throw new LeaveException('Pengajuan ini tidak bisa Anda proses (mungkin sudah diproses orang lain).');
            }

            $r->currentApproval()->update([
                'status' => 'approved', 'approver_id' => $by->id, 'note' => $note, 'acted_at' => now(),
            ]);
            $r->load('approvals');

            if ($r->currentApproval()) {                  // masih ada langkah berikutnya
                $this->notifyNext($r);
                return;
            }

            $r->update(['status' => 'approved']);
            $this->deduct($r, $by);
            $this->notifyEmployee($r, 'Pengajuan disetujui', 'success');
        });

        activity()->causedBy($by)->performedOn($request)->log('Cuti disetujui' . ($note ? ": {$note}" : ''));
    }

    public function reject(LeaveRequest $request, User $by, string $note): void
    {
        DB::transaction(function () use ($request, $by, $note) {
            $r = LeaveRequest::lockForUpdate()->with('approvals', 'employee.user', 'type')->findOrFail($request->id);

            if (! $this->canAct($r, $by)) {
                throw new LeaveException('Pengajuan ini tidak bisa Anda proses (mungkin sudah diproses orang lain).');
            }

            $r->currentApproval()->update([
                'status' => 'rejected', 'approver_id' => $by->id, 'note' => $note, 'acted_at' => now(),
            ]);
            $r->approvals()->where('status', 'pending')->update(['status' => 'skipped']);
            $r->update(['status' => 'rejected']);

            $this->notifyEmployee($r, 'Pengajuan ditolak', 'danger', $note);
        });

        activity()->causedBy($by)->performedOn($request)->log("Cuti ditolak: {$note}");
    }

    public function cancel(LeaveRequest $request, User $by): void
    {
        DB::transaction(function () use ($request, $by) {
            $r = LeaveRequest::lockForUpdate()->with('approvals', 'employee', 'type')->findOrFail($request->id);

            $isOwner = $by->employee?->id === $r->employee_id;
            if (! $isOwner && ! $by->hasAnyRole(['super_admin', 'hrd'])) {
                throw new LeaveException('Anda tidak berhak membatalkan pengajuan ini.');
            }
            if (! $r->canBeCancelled()) {
                throw new LeaveException('Pengajuan ini sudah tidak bisa dibatalkan.');
            }

            if ($r->status === 'approved') {
                $this->refund($r, $by);
            }

            $r->approvals()->where('status', 'pending')->update(['status' => 'skipped']);
            $r->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });

        activity()->causedBy($by)->performedOn($request)->log('Cuti dibatalkan');
    }

    private function deduct(LeaveRequest $r, ?User $by): void
    {
        if (! $r->type->deducts_balance) return;

        $key = ['employee_id' => $r->employee_id, 'leave_type_id' => $r->leave_type_id, 'year' => $r->start_date->year];

        $b = LeaveBalance::where($key)->lockForUpdate()->first();
        if (! $b) {
            $this->ensureBalances($r->employee, $r->start_date->year);
            $b = LeaveBalance::where($key)->lockForUpdate()->first();
        }
        if (! $b) return;

        $b->increment('used', $r->days);
        $b->logs()->create([
            'change'         => -$r->days,
            'reason'         => "{$r->type->name} {$r->start_date->format('d/m/Y')}" . ($r->days > 1 ? " ({$r->days} hari)" : ''),
            'reference_type' => LeaveRequest::class,
            'reference_id'   => $r->id,
            'created_by'     => $by?->id,
        ]);
    }

    private function refund(LeaveRequest $r, User $by): void
    {
        if (! $r->type->deducts_balance) return;

        $b = LeaveBalance::where([
            'employee_id' => $r->employee_id, 'leave_type_id' => $r->leave_type_id, 'year' => $r->start_date->year,
        ])->lockForUpdate()->first();
        if (! $b) return;

        $b->decrement('used', $r->days);
        $b->logs()->create([
            'change'         => $r->days,
            'reason'         => "Pembatalan {$r->type->name} {$r->start_date->format('d/m/Y')}",
            'reference_type' => LeaveRequest::class,
            'reference_id'   => $r->id,
            'created_by'     => $by->id,
        ]);
    }

    /* ---------- Integrasi absensi ---------- */

    /** Cuti/izin/sakit satu hari penuh yang sudah disetujui. Setengah hari tidak memblokir absen. */
    public function onLeave(Employee $e, ?Carbon $date = null): ?LeaveRequest
    {
        $d = ($date ?? today())->toDateString();

        return LeaveRequest::with('type')
            ->where('employee_id', $e->id)->where('status', 'approved')->where('is_half_day', false)
            ->where('start_date', '<=', $d)->where('end_date', '>=', $d)->first();
    }

    /* ---------- Notifikasi ---------- */

    private function notifyNext(LeaveRequest $r): void
    {
        $a = $r->currentApproval();
        if (! $a) return;

        $users = match ($a->role) {
            'atasan' => collect([$r->employee->manager?->user])->filter(),
            'hrd'    => User::role('hrd')->where('is_active', true)->get(),
            default  => collect(),
        };
        if ($users->isEmpty()) return;

        $period = $r->start_date->format('d M')
            . ($r->end_date->isSameDay($r->start_date) ? '' : ' - ' . $r->end_date->format('d M'));

        FilamentNotification::make()
            ->title('Pengajuan menunggu persetujuan Anda')
            ->body("{$r->employee->name} · {$r->type->name} · {$period} ({$r->days} hari)")
            ->icon('heroicon-o-calendar-days')
            ->warning()
            ->sendToDatabase($users);
    }

    private function notifyEmployee(LeaveRequest $r, string $title, string $color, ?string $note = null): void
    {
        $user = $r->employee->user;
        if (! $user) return;

        $n = FilamentNotification::make()
            ->title($title)
            ->body("{$r->type->name} {$r->start_date->format('d M Y')}" . ($note ? " · {$note}" : ''))
            ->icon('heroicon-o-calendar-days');

        $color === 'success' ? $n->success() : $n->danger();
        $n->sendToDatabase($user);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}