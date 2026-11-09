<?php

namespace App\Filament\Resources\LeaveBalances;

use App\Exceptions\LeaveException;
use App\Filament\Resources\LeaveBalances\Pages\ManageLeaveBalances;
use App\Models\LeaveBalance;
use App\Services\LeaveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LeaveBalanceResource extends Resource
{
    protected static ?string $model = LeaveBalance::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-scale';
    protected static string | UnitEnum | null $navigationGroup = 'Cuti & Izin';
    protected static ?string $modelLabel = 'Saldo Cuti';
    protected static ?string $pluralModelLabel = 'Saldo Cuti';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee.department', 'type']);
    }

    public static function table(Table $table): Table
    {
        $manage = fn () => auth()->user()->hasAnyRole(['super_admin', 'hrd']);

        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('employee.nik')->label('NIK')->searchable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable(),
                TextColumn::make('employee.department.name')->label('Departemen')->toggleable(),
                TextColumn::make('type.name')->label('Jenis'),
                TextColumn::make('year')->label('Tahun')->sortable(),
                TextColumn::make('quota')->label('Kuota'),
                TextColumn::make('carried_over')->label('Carry-over'),
                TextColumn::make('used')->label('Terpakai'),
                TextColumn::make('remaining')->label('Sisa')->badge()
                    ->state(fn (LeaveBalance $record) => $record->remaining + 0)
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 3 ? 'warning' : 'success')),
            ])
            ->filters([
                SelectFilter::make('year')->label('Tahun')->default((string) now()->year)
                    ->options(fn () => collect(range(now()->year - 2, now()->year + 1))
                        ->mapWithKeys(fn ($y) => [$y => (string) $y])->all()),
                SelectFilter::make('leave_type_id')->label('Jenis')->relationship('type', 'name'),
                SelectFilter::make('department')->label('Departemen')
                    ->relationship('employee.department', 'name'),
            ])
            ->recordActions([
                Action::make('adjust')->label('Sesuaikan')->icon('heroicon-o-adjustments-horizontal')
                    ->visible($manage)
                    ->schema([
                        TextInput::make('change')->label('Perubahan kuota (hari)')
                            ->numeric()->step(0.5)->required()
                            ->helperText('Positif menambah, negatif mengurangi. Contoh: 2 atau -1.5'),
                        Textarea::make('reason')->label('Alasan')->required()->maxLength(200),
                    ])
                    ->action(function (LeaveBalance $record, array $data) {
                        try {
                            app(LeaveService::class)->adjust($record, (float) $data['change'], $data['reason'], auth()->user());
                            Notification::make()->title('Saldo disesuaikan')->success()->send();
                        } catch (LeaveException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('logs')->label('Riwayat')->icon('heroicon-o-clock')->color('gray')
                    ->modalHeading(fn (LeaveBalance $record) => "Mutasi saldo: {$record->employee->name} ({$record->type->name} {$record->year})")
                    ->modalContent(fn (LeaveBalance $record) => view('filament.leave-balance-logs', [
                        'logs' => $record->logs()->with('creator')->latest('id')->limit(50)->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLeaveBalances::route('/')];
    }
}