<?php

namespace App\Filament\Pages;

use App\Exports\RecapExport;
use App\Models\Department;
use App\Services\AttendanceReport;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class RekapAbsensi extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';
    protected static string | UnitEnum | null $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Rekap Absensi';
    protected static ?string $title = 'Rekap Absensi';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.rekap-absensi';

    public int $year = 0;
    public int $month = 0;
    public ?string $departmentId = null;
    public string $tab = 'matriks';      // matriks | ringkasan | departemen
    public string $sort = 'nama';        // nama | telat | alfa

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'hrd', 'atasan']) ?? false;
    }

    public function mount(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    protected function period(): array
    {
        $from = Carbon::create($this->year, min(12, max(1, $this->month)), 1)->startOfDay();

        return [$from, $from->copy()->endOfMonth()->startOfDay()];
    }

    #[Computed]
    public function recap(): array
    {
        [$from, $to] = $this->period();

        return app(AttendanceReport::class)->build(
            auth()->user(), $from, $to, $this->departmentId ? (int) $this->departmentId : null
        );
    }

    public function sortedRows(): array
    {
        $rows = collect($this->recap['rows']);

        return match ($this->sort) {
            'telat' => $rows->sortByDesc(fn ($r) => $r['sum']['late_min'])->values()->all(),
            'alfa'  => $rows->sortByDesc(fn ($r) => $r['sum']['alfa'])->values()->all(),
            default => $rows->all(),
        };
    }

    public function departments(): array
    {
        return Department::orderBy('name')->pluck('name', 'id')->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Ekspor Excel')->icon('heroicon-o-table-cells')->color('success')
                ->action(fn () => $this->downloadExcel()),
            Action::make('pdf')->label('Ekspor PDF')->icon('heroicon-o-document-arrow-down')->color('danger')
                ->action(fn () => $this->downloadPdf()),
        ];
    }

    protected function fileStem(): string
    {
        return 'rekap-absensi-' . $this->year . '-' . str_pad((string) $this->month, 2, '0', STR_PAD_LEFT);
    }

    public function downloadExcel()
    {
        $data = $this->recap;

        return response()->streamDownload(function () use ($data) {
            echo Excel::raw(new RecapExport($data), ExcelFormat::XLSX);
        }, $this->fileStem() . '.xlsx');
    }

    public function downloadPdf()
    {
        $pdf = Pdf::loadView('reports.recap-pdf', [
            'data' => $this->recap, 'company' => config('attendance.report.company_name'),
        ])->setPaper('a4', 'landscape')->output();

        return response()->streamDownload(fn () => print($pdf), $this->fileStem() . '.pdf');
    }
}