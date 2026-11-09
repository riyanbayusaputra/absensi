<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Support\TeamScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TodayAttendances extends TableWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'hrd', 'atasan']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Absensi hari ini (terbaru)')
            ->query(fn () => TeamScope::restrict(
                Attendance::query()->with('employee')->whereDate('date', today())->latest('updated_at'),
                auth()->user()
            ))
            ->columns([
                TextColumn::make('employee.name')->label('Karyawan'),
                TextColumn::make('check_in_at')->label('Masuk')->dateTime('H:i')->placeholder('-'),
                TextColumn::make('check_out_at')->label('Pulang')->dateTime('H:i')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'terlambat' ? 'Terlambat' : 'Tepat waktu')
                    ->color(fn (string $state) => $state === 'terlambat' ? 'warning' : 'success'),
                TextColumn::make('in_distance_m')->label('Jarak')->suffix(' m'),
            ])
            ->paginated([8])
            ->emptyStateHeading('Belum ada yang absen hari ini');
    }
}