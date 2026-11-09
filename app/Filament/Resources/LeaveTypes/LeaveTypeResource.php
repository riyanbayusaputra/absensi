<?php

namespace App\Filament\Resources\LeaveTypes;

use App\Filament\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Models\LeaveType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static string | UnitEnum | null $navigationGroup = 'Cuti & Izin';
    protected static ?string $modelLabel = 'Jenis Cuti';
    protected static ?string $pluralModelLabel = 'Jenis Cuti';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(80),
            TextInput::make('code')->label('Kode')->required()->maxLength(10)
                ->unique(ignoreRecord: true)
                ->helperText('Kode CT dipakai untuk "Sisa cuti tahunan" di beranda karyawan.'),
            Toggle::make('deducts_balance')->label('Memotong saldo')->default(true)->live(),
            TextInput::make('annual_quota')->label('Kuota per tahun (hari)')
                ->numeric()->step(0.5)->default(0)
                ->visible(fn ($get) => (bool) $get('deducts_balance')),
            TextInput::make('carry_over_max')->label('Maks carry-over (hari)')
                ->numeric()->step(0.5)->default(0)
                ->visible(fn ($get) => (bool) $get('deducts_balance')),
            TextInput::make('min_notice_days')->label('Diajukan minimal H- (hari)')->numeric()->default(0),
            TextInput::make('backdate_days')->label('Boleh mundur (hari)')->numeric()->default(0)
                ->helperText('0 = tidak boleh tanggal lampau'),
            TextInput::make('max_days_per_request')->label('Maks hari per pengajuan')->numeric()->nullable(),
            Toggle::make('requires_attachment')->label('Lampiran wajib'),
            Toggle::make('allow_half_day')->label('Boleh setengah hari'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->badge(),
                TextColumn::make('name')->label('Nama')->searchable(),
                IconColumn::make('deducts_balance')->label('Potong saldo')->boolean(),
                TextColumn::make('annual_quota')->label('Kuota')->suffix(' hari'),
                TextColumn::make('min_notice_days')->label('Min H-'),
                IconColumn::make('requires_attachment')->label('Lampiran')->boolean(),
                IconColumn::make('allow_half_day')->label('1/2 hari')->boolean(),
                ToggleColumn::make('is_active')->label('Aktif')
                    ->disabled(fn () => ! auth()->user()->hasAnyRole(['super_admin', 'hrd'])),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLeaveTypes::route('/')];
    }
}