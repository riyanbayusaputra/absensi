<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Support\TeamScope;
use Filament\Widgets\ChartWidget;

class AttendanceTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'hrd', 'atasan']) ?? false;
    }

    public function getHeading(): ?string
    {
        return 'Tren kehadiran 14 hari terakhir';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn ($i) => today()->subDays($i));

        $rows = TeamScope::restrict(
            Attendance::query()->whereBetween('date', [$days->first()->toDateString(), today()->toDateString()]),
            auth()->user()
        )
            ->selectRaw("date, COUNT(*) as total, SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as late")
            ->groupBy('date')->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        return [
            'labels'   => $days->map(fn ($d) => $d->translatedFormat('d M'))->all(),
            'datasets' => [
                ['label' => 'Hadir', 'data' => $days->map(fn ($d) => (int) ($rows[$d->toDateString()]->total ?? 0))->all(), 'borderColor' => '#16a34a'],
                ['label' => 'Terlambat', 'data' => $days->map(fn ($d) => (int) ($rows[$d->toDateString()]->late ?? 0))->all(), 'borderColor' => '#f59e0b'],
            ],
        ];
    }
}