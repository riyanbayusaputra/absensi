<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Exceptions\LeaveException;
use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string | UnitEnum | null $navigationGroup = 'Cuti & Izin';
    protected static ?string $modelLabel = 'Pengajuan Cuti';
    protected static ?string $pluralModelLabel = 'Pengajuan Cuti';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee.department', 'type', 'approvals.approver']);
        $user  = auth()->user();

        // atasan hanya melihat anggota timnya
        if ($user->hasRole('atasan') && ! $user->hasAnyRole(['super_admin', 'hrd'])) {
            $query->whereHas('employee', fn ($e) => $e->where('manager_id', $user->employee?->id ?? 0));
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $q    = LeaveRequest::query();

        if ($user->hasRole('super_admin')) {
            $q->where('status', 'pending');
        } else {
            $q->where(function ($w) use ($user) {
                if ($user->hasRole('hrd')) {
                    $w->orWhere(fn ($x) => $x->awaiting('hrd'));
                }
                if ($user->hasRole('atasan')) {
                    $w->orWhere(fn ($x) => $x->awaiting('atasan')
                        ->whereHas('employee', fn ($e) => $e->where('manager_id', $user->employee?->id ?? 0)));
                }
            });
        }

        $n = $q->count();

        return $n ?: null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        $svc = fn () => app(LeaveService::class);

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable(),
                TextColumn::make('employee.department.name')->label('Departemen')->toggleable(),
                TextColumn::make('type.name')->label('Jenis'),
                TextColumn::make('period')->label('Periode')
                    ->state(fn (LeaveRequest $record) => $record->start_date->format('d M Y')
                        . ($record->end_date->isSameDay($record->start_date) ? '' : ' – ' . $record->end_date->format('d M Y'))),
                TextColumn::make('days')->label('Hari')->state(fn (LeaveRequest $record) => $record->days + 0),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (LeaveRequest $record) => $record->status_label)
                    ->color(fn (LeaveRequest $record) => match ($record->status) {
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        'cancelled' => 'gray',
                        default     => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan',
                ]),
                SelectFilter::make('leave_type_id')->label('Jenis')->relationship('type', 'name'),
                SelectFilter::make('department')->label('Departemen')
                    ->relationship('employee.department', 'name'),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('end_date', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('start_date', '<=', $v))),
            ])
            ->recordActions([
                Action::make('detail')->label('Detail')->icon('heroicon-o-eye')->color('gray')
                    ->modalHeading(fn (LeaveRequest $record) => "{$record->type->name}: {$record->employee->name}")
                    ->modalContent(fn (LeaveRequest $record) => view('filament.leave-detail', ['r' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Action::make('approve')->label('Setujui')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (LeaveRequest $record) => $svc()->canAct($record, auth()->user()))
                    ->schema([
                        Textarea::make('note')->label('Catatan (opsional)')->maxLength(300),
                    ])
                    ->action(function (LeaveRequest $record, array $data) use ($svc) {
                        try {
                            $svc()->approve($record, auth()->user(), $data['note'] ?? null);
                            Notification::make()->title('Pengajuan disetujui')->success()->send();
                        } catch (LeaveException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('reject')->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (LeaveRequest $record) => $svc()->canAct($record, auth()->user()))
                    ->schema([
                        Textarea::make('note')->label('Alasan penolakan')->required()->maxLength(300),
                    ])
                    ->action(function (LeaveRequest $record, array $data) use ($svc) {
                        try {
                            $svc()->reject($record, auth()->user(), $data['note']);
                            Notification::make()->title('Pengajuan ditolak')->send();
                        } catch (LeaveException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('cancel')->label('Batalkan')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (LeaveRequest $record) => $record->canBeCancelled()
                        && auth()->user()->hasAnyRole(['super_admin', 'hrd']))
                    ->requiresConfirmation()
                    ->modalDescription('Jika sudah disetujui, saldo cuti akan dikembalikan.')
                    ->action(function (LeaveRequest $record) use ($svc) {
                        try {
                            $svc()->cancel($record, auth()->user());
                            Notification::make()->title('Pengajuan dibatalkan')->send();
                        } catch (LeaveException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLeaveRequests::route('/')];
    }
}