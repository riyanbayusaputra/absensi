<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class JadwalMingguan extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar';
    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';
    protected static ?string $navigationLabel = 'Jadwal Mingguan';
    protected static ?string $title = 'Jadwal Mingguan';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.jadwal-mingguan';

    public string $week = '';              // tanggal Senin, Y-m-d
    public ?string $departmentId = null;
    public array $grid = [];               // [employee_id][Y-m-d] => true bila LIBUR
    public array $configured = [];         // [employee_id] => sudah diatur untuk minggu ini?

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'hrd', 'atasan']) ?? false;
    }

    public static function canEdit(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'hrd']) ?? false;
    }

    public function canEditSchedule(): bool
    {
        return static::canEdit();
    }

    public function mount(): void
    {
        $this->week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->loadGrid();
    }

    /* ---------- data ---------- */

    protected function monday(): Carbon
    {
        return Carbon::parse($this->week)->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** @return Carbon[] */
    public function days(): array
    {
        $m = $this->monday();

        return collect(range(0, 6))->map(fn ($i) => $m->copy()->addDays($i))->all();
    }

    public function employees(): Collection
    {
        $user = auth()->user();

        $q = Employee::query()->with('department')->where('status', 'aktif')
            ->when($this->departmentId, fn ($query, $v) => $query->where('department_id', $v))
            ->orderBy('name');

        if ($user->hasRole('atasan') && ! $user->hasAnyRole(['super_admin', 'hrd'])) {
            $q->where('manager_id', $user->employee?->id ?? 0);
        }

        return $q->get();
    }

    public function holidays(): array
    {
        $d = $this->days();

        return Holiday::whereBetween('date', [$d[0]->toDateString(), $d[6]->toDateString()])->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all();
    }

    public function departments(): array
    {
        return \App\Models\Department::orderBy('name')->pluck('name', 'id')->all();
    }

    protected function defaultOff(Carbon $d): bool
    {
        return ! in_array($d->dayOfWeekIso, config('attendance.leave.work_days', [1, 2, 3, 4, 5]), true);
    }

    protected function explicitFor(array $ids, Carbon $from, Carbon $to): array
    {
        return WorkSchedule::whereIn('employee_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->groupBy('employee_id')
            ->map(fn ($g) => $g->mapWithKeys(fn ($r) => [$r->date->toDateString() => (bool) $r->is_off])->all())
            ->all();
    }

    public function loadGrid(): void
    {
        $employees = $this->employees();
        $days      = $this->days();
        $explicit  = $this->explicitFor($employees->pluck('id')->all(), $days[0], $days[6]);

        $this->grid = [];
        $this->configured = [];

        foreach ($employees as $e) {
            foreach ($days as $d) {
                $ds = $d->toDateString();
                $this->grid[$e->id][$ds] = $explicit[$e->id][$ds] ?? $this->defaultOff($d);
            }
            $this->configured[$e->id] = ! empty($explicit[$e->id]);
        }
    }

    /* ---------- navigasi ---------- */

    public function prevWeek(): void
    {
        $this->week = $this->monday()->subWeek()->toDateString();
        $this->loadGrid();
    }

    public function nextWeek(): void
    {
        $this->week = $this->monday()->addWeek()->toDateString();
        $this->loadGrid();
    }

    public function thisWeek(): void
    {
        $this->week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->loadGrid();
    }

    public function updatedWeek(): void
    {
        try {
            $this->week = Carbon::parse($this->week)->startOfWeek(Carbon::MONDAY)->toDateString();
        } catch (\Throwable) {
            $this->week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }
        $this->loadGrid();
    }

    public function updatedDepartmentId(): void
    {
        $this->loadGrid();
    }

    /* ---------- aksi ---------- */

    public function save(): void
    {
        abort_unless(static::canEdit(), 403);

        $now  = now();
        $rows = [];

        foreach ($this->employees() as $e) {
            foreach ($this->days() as $d) {
                $ds = $d->toDateString();
                $rows[] = [
                    'employee_id' => $e->id,
                    'date'        => $ds,
                    'is_off'      => (bool) ($this->grid[$e->id][$ds] ?? $this->defaultOff($d)),
                    'created_by'  => auth()->id(),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        if ($rows) {
            DB::transaction(fn () => WorkSchedule::upsert($rows, ['employee_id', 'date'], ['is_off', 'created_by', 'updated_at']));
        }

        $d = $this->days();
        activity()->causedBy(auth()->user())->log('Jadwal minggu ' . $d[0]->format('d/m') . '-' . $d[6]->format('d/m/Y') . ' disimpan (' . count($rows) . ' baris)');

        $this->loadGrid();
        Notification::make()->title('Jadwal minggu ini disimpan')->success()->send();
    }

    public function fillDefault(): void
    {
        foreach ($this->grid as $id => $cells) {
            foreach (array_keys($cells) as $ds) {
                $this->grid[$id][$ds] = $this->defaultOff(Carbon::parse($ds));
            }
        }

        Notification::make()->title('Diisi pola standar. Klik Simpan jadwal untuk menyimpan.')->warning()->send();
    }

    public function copyPrevious(): void
    {
        $days    = $this->days();
        $prevMon = $this->monday()->subWeek();
        $ids     = array_keys($this->grid);
        $prev    = $this->explicitFor($ids, $prevMon, $prevMon->copy()->addDays(6));
        $copied  = 0;

        foreach ($ids as $id) {
            if (empty($prev[$id])) continue;

            foreach ($days as $i => $d) {
                $pds = $prevMon->copy()->addDays($i)->toDateString();
                if (array_key_exists($pds, $prev[$id])) {
                    $this->grid[$id][$d->toDateString()] = $prev[$id][$pds];
                }
            }
            $copied++;
        }

        Notification::make()
            ->title("Disalin dari minggu lalu untuk {$copied} karyawan. Klik Simpan jadwal untuk menyimpan.")
            ->warning()->send();
    }

    public function copyForward(int $weeks): void
    {
        abort_unless(static::canEdit(), 403);

        $this->save();

        $now  = now();
        $mon  = $this->monday();
        $rows = [];

        foreach ($this->employees() as $e) {
            for ($w = 1; $w <= $weeks; $w++) {
                foreach (range(0, 6) as $i) {
                    $src = $mon->copy()->addDays($i);
                    $rows[] = [
                        'employee_id' => $e->id,
                        'date'        => $mon->copy()->addDays($w * 7 + $i)->toDateString(),
                        'is_off'      => (bool) ($this->grid[$e->id][$src->toDateString()] ?? $this->defaultOff($src)),
                        'created_by'  => auth()->id(),
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            WorkSchedule::upsert($chunk, ['employee_id', 'date'], ['is_off', 'created_by', 'updated_at']);
        }

        activity()->causedBy(auth()->user())->log("Jadwal minggu ini diterapkan ke {$weeks} minggu berikutnya");
        Notification::make()->title("Pola diterapkan ke {$weeks} minggu berikutnya")->success()->send();
    }

    protected function getHeaderActions(): array
    {
        if (! static::canEdit()) {
            return [];
        }

        return [
            Action::make('default')->label('Isi pola standar')->color('gray')->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Mengisi ulang minggu ini dengan pola standar (Sabtu dan Minggu libur). Belum disimpan sebelum Anda klik Simpan jadwal.')
                ->action(fn () => $this->fillDefault()),

            Action::make('copyPrev')->label('Salin minggu lalu')->color('gray')->icon('heroicon-o-document-duplicate')
                ->action(fn () => $this->copyPrevious()),

            Action::make('copyNext')->label('Terapkan ke minggu depan')->color('gray')->icon('heroicon-o-forward')
                ->modalDescription('Jadwal minggu ini disimpan dan disalin ke minggu-minggu berikutnya. Jadwal yang sudah ada di minggu itu akan ditimpa.')
                ->schema([
                    Select::make('weeks')->label('Jumlah minggu ke depan')
                        ->options([1 => '1 minggu', 2 => '2 minggu', 3 => '3 minggu', 4 => '4 minggu'])
                        ->default(1)->required(),
                ])
                ->action(fn (array $data) => $this->copyForward((int) $data['weeks'])),

            Action::make('save')->label('Simpan jadwal')->icon('heroicon-o-check')->color('primary')
                ->action(fn () => $this->save()),
        ];
    }
}