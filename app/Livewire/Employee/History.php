<?php

namespace App\Livewire\Employee;

use App\Models\Employee;
use App\Services\AttendanceCalendar;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile')]
class History extends Component
{
    public int $year = 0;
    public int $mon = 0;
    public string $mode = 'daftar';      // daftar | kalender
    public string $filter = 'semua';     // semua | hadir | terlambat | alfa | cuti
    public ?string $selected = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->employee, 403, 'Akun belum terhubung dengan data karyawan.');

        $this->year = now()->year;
        $this->mon  = now()->month;
    }

    private function employee(): Employee
    {
        return auth()->user()->employee;
    }

    /** Bulan yang dilihat, dibatasi: tidak boleh di masa depan, dan maksimal sejak Januari tahun lalu. */
    private function monthDate(): Carbon
    {
        $max = now()->startOfMonth();
        $min = now()->startOfYear()->subYear();

        try {
            $m = Carbon::create($this->year, max(1, min(12, $this->mon)), 1)->startOfDay();
        } catch (\Throwable) {
            return $max;
        }

        return $m->gt($max) ? $max : ($m->lt($min) ? $min : $m);
    }

    private function syncMonth(): void
    {
        $m = $this->monthDate();

        $this->year     = $m->year;
        $this->mon      = $m->month;
        $this->selected = ($this->mode === 'kalender' && $m->isSameMonth(today())) ? today()->toDateString() : null;
    }

    public function updatedYear(): void
    {
        $this->syncMonth();
    }

    public function updatedMon(): void
    {
        $this->syncMonth();
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['daftar', 'kalender'], true) ? $mode : 'daftar';

        if ($this->mode === 'kalender' && ! $this->selected && $this->monthDate()->isSameMonth(today())) {
            $this->selected = today()->toDateString();
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter   = in_array($filter, ['semua', 'hadir', 'terlambat', 'alfa', 'cuti'], true) ? $filter : 'semua';
        $this->selected = null;
    }

    /** Ketuk baris (daftar) atau tanggal (kalender) di bulan yang sedang dilihat. */
    public function selectDay(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! str_starts_with($date, $this->monthDate()->format('Y-m'))) {
            return;
        }

        $this->selected = ($this->mode === 'daftar' && $this->selected === $date) ? null : $date;
    }

    /** Dari kartu Hari ini / Kemarin: pindah ke bulannya lalu buka detail tanggal itu. */
    public function openDay(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }

        $d = Carbon::parse($date)->startOfDay();
        if ($d->gt(today())) {
            return;
        }

        $this->year   = $d->year;
        $this->mon    = $d->month;
        $this->filter = 'semua';

        $m = $this->monthDate();
        if (! $d->isSameMonth($m)) {
            return;
        }
        $this->year = $m->year;
        $this->mon  = $m->month;

        // hari libur tidak ada di Daftar, jadi tampilkan lewat Kalender
        if (in_array($this->dayFor($d)['code'], ['L', ''], true)) {
            $this->mode = 'kalender';
        }

        $this->selected = $date;
    }

    #[Computed]
    public function calendar(): array
    {
        return app(AttendanceCalendar::class)->month($this->employee(), $this->monthDate());
    }

    /** Data satu hari; ambil dari bulan yang sedang dilihat, atau hitung bulannya bila berbeda. */
    private function dayFor(Carbon $date): array
    {
        $ds  = $date->toDateString();
        $cal = $this->calendar;

        if (isset($cal['days'][$ds])) {
            return $cal['days'][$ds];
        }

        $other = app(AttendanceCalendar::class)->month($this->employee(), $date);

        return $other['days'][$ds] ?? [
            'date' => $date->copy(), 'code' => '', 'att' => null, 'rejected' => false, 'holiday' => null,
            'leave' => null, 'no_out' => false, 'working' => false, 'corrected' => false,
        ];
    }

    public function render()
    {
        $cal   = $this->calendar;
        $today = today();

        // catatan = hari yang sudah lewat/berjalan dan bukan hari libur
        $rows = collect($cal['days'])->filter(fn ($d) => ! in_array($d['code'], ['', 'L'], true) && $d['date']->lte($today));

        $counts = [
            'semua'     => $rows->count(),
            'hadir'     => $rows->whereIn('code', ['H', 'T'])->count(),
            'terlambat' => $rows->where('code', 'T')->count(),
            'alfa'      => $rows->where('code', 'A')->count(),
            'cuti'      => $rows->whereIn('code', ['C', 'S', 'I', 'K'])->count(),
        ];

        $list = $rows->filter(fn ($d) => match ($this->filter) {
            'hadir'     => in_array($d['code'], ['H', 'T'], true),
            'terlambat' => $d['code'] === 'T',
            'alfa'      => $d['code'] === 'A',
            'cuti'      => in_array($d['code'], ['C', 'S', 'I', 'K'], true),
            default     => true,
        })->reverse()->values();

        $m = $this->monthDate();

        return view('livewire.employee.history', [
            'cal'      => $cal,
            'list'     => $list,
            'counts'   => $counts,
            'quick'    => [
                'Hari ini' => $this->dayFor($today),
                'Kemarin'  => $this->dayFor($today->copy()->subDay()),
            ],
            'selected' => ($this->selected && isset($cal['days'][$this->selected])) ? $this->selected : null,
            'ym'       => $m->format('Y-m'),
            'years'    => [now()->year - 1, now()->year],
            'backdate' => (int) config('attendance.correction.backdate_days', 14),
        ]);
    }
}