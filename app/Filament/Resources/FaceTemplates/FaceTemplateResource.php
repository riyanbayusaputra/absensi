<?php

namespace App\Filament\Resources\FaceTemplates;

use App\Filament\Resources\FaceTemplates\Pages\ManageFaceTemplates;
use App\Models\FaceTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FaceTemplateResource extends Resource
{
    protected static ?string $model = FaceTemplate::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-face-smile';
    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';
    protected static ?string $modelLabel = 'Registrasi Wajah';
    protected static ?string $pluralModelLabel = 'Registrasi Wajah';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = FaceTemplate::where('status', 'pending')->count();

        return $n ?: null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }

    protected static function canReview(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'hrd']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee', 'reviewer']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('photo')->label('Foto')->circular()->disk('public')->visibility('public')
                    ->state(fn (FaceTemplate $record) => route('face.photo', $record)),
                TextColumn::make('employee.nik')->label('NIK')->searchable(),
                TextColumn::make('employee.name')->label('Karyawan')->searchable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'pending'  => 'warning',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('reviewer.name')->label('Ditinjau oleh')->toggleable(),
                TextColumn::make('reject_reason')->label('Alasan')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->default('pending')->options([
                    'pending'    => 'Pending',
                    'approved'   => 'Approved',
                    'rejected'   => 'Rejected',
                    'superseded' => 'Superseded',
                ]),
            ])
            ->recordActions([
                Action::make('approve')->label('Setujui')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (FaceTemplate $record) => $record->status === 'pending' && static::canReview())
                    ->requiresConfirmation()
                    ->action(function (FaceTemplate $record) {
                        $record->approve(auth()->user());
                        Notification::make()->title('Registrasi wajah disetujui')->success()->send();
                    }),

                Action::make('reject')->label('Tolak')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (FaceTemplate $record) => $record->status === 'pending' && static::canReview())
                    ->schema([
                        Textarea::make('reason')->label('Alasan penolakan')->required()->maxLength(200),
                    ])
                    ->action(function (FaceTemplate $record, array $data) {
                        $record->reject(auth()->user(), $data['reason']);
                        Notification::make()->title('Registrasi wajah ditolak')->send();
                    }),

                Action::make('reset')->label('Reset')
                    ->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (FaceTemplate $record) => $record->status === 'approved' && static::canReview())
                    ->requiresConfirmation()
                    ->modalDescription('Karyawan tidak bisa absen sampai mendaftarkan dan mendapat persetujuan wajah baru.')
                    ->action(function (FaceTemplate $record) {
                        $record->reset(auth()->user());
                        Notification::make()->title('Registrasi wajah direset')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaceTemplates::route('/')];
    }
}