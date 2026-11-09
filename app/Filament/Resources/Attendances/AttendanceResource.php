<?php

namespace App\Filament\Resources\Attendances;

use App\Filament\Resources\Attendances\Pages\ManageAttendances;
use App\Models\Attendance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string | UnitEnum | null $navigationGroup = 'Absensi';
    protected static ?string $modelLabel = 'Absensi';
    protected static ?string $pluralModelLabel = 'Data Absensi';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee.department', 'shift', 'inLocation']);
        $user  = auth()->user();

        // atasan hanya melihat anggota timnya
        if ($user->hasRole('atasan') && ! $user->hasAnyRole(['super_admin', 'hrd'])) {
            $query->whereHas('employee', fn ($e) => $e->where('manager_id', $user->employee?->id ?? 0));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        $canSeePhoto = fn () => auth()->user()->hasAnyRole(['super_admin', 'hrd']);

        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable(),
                TextColumn::make('employee.department.name')->label('Departemen')->toggleable(),
                TextColumn::make('shift.name')->label('Shift')->toggleable(),
                TextColumn::make('check_in_at')->label('Masuk')->dateTime('H:i'),
                TextColumn::make('check_out_at')->label('Pulang')->dateTime('H:i')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'terlambat' ? 'Terlambat' : 'Tepat waktu')
                    ->color(fn (string $state) => $state === 'terlambat' ? 'danger' : 'success'),
                TextColumn::make('late_minutes')->label('Telat')->suffix(' mnt'),
                TextColumn::make('early_leave_minutes')->label('Pulang cepat')->suffix(' mnt')->toggleable(),
                TextColumn::make('work_minutes')->label('Durasi')
                    ->formatStateUsing(fn ($state) => $state ? intdiv($state, 60) . 'j ' . ($state % 60) . 'm' : '-'),
                TextColumn::make('in_distance_m')->label('Jarak')->suffix(' m')->toggleable(),
                TextColumn::make('in_face_score')->label('Skor wajah')->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('in_photo')->label('Foto masuk')->circular()
                    ->state(fn (Attendance $record) => $record->in_photo ? route('attendance.photo', [$record, 'in']) : null)
                
                    ->disk('public')->visibility('public')->visible($canSeePhoto),
                ImageColumn::make('out_photo')->label('Foto pulang')->circular()
                    ->state(fn (Attendance $record) => $record->out_photo ? route('attendance.photo', [$record, 'out']) : null)
                    ->disk('public')->visibility('public')->visible($canSeePhoto),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->default(now()->startOfMonth()),
                        DatePicker::make('until')->label('Sampai')->default(now()),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
                SelectFilter::make('status')->label('Status')
                    ->options(['tepat_waktu' => 'Tepat waktu', 'terlambat' => 'Terlambat']),
                SelectFilter::make('department')->label('Departemen')
                    ->relationship('employee.department', 'name'),
            ])
            ->recordActions([
            Action::make('peta')->label('Peta')->icon('heroicon-o-map-pin')
    ->visible(fn (Attendance $record) => $record->in_lat !== null || $record->out_lat !== null)
    ->modalHeading(fn (Attendance $record) => 'Lokasi absen: ' . $record->employee->name)
    ->modalWidth('3xl')
    ->modalContent(function (Attendance $record) {
        $record->loadMissing('inLocation', 'outLocation');
        $office = $record->inLocation ?? $record->outLocation;

        return view('filament.attendance-map', [
            'a' => $record,
            'payload' => [
                'office' => $office ? [
                    'name' => $office->name, 'lat' => (float) $office->latitude,
                    'lng' => (float) $office->longitude, 'radius' => (int) $office->radius_m,
                ] : null,
                'in' => $record->in_lat !== null ? [
                    'lat' => (float) $record->in_lat, 'lng' => (float) $record->in_lng,
                    'acc' => (float) $record->in_accuracy, 'time' => $record->check_in_at?->format('H:i'),
                ] : null,
                'out' => $record->out_lat !== null ? [
                    'lat' => (float) $record->out_lat, 'lng' => (float) $record->out_lng,
                    'acc' => (float) $record->out_accuracy, 'time' => $record->check_out_at?->format('H:i'),
                ] : null,
            ],
        ]);
    })
    ->modalSubmitAction(false)
    ->modalCancelActionLabel('Tutup'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAttendances::route('/')];
    }
}