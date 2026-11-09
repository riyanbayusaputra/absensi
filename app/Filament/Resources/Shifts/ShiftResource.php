<?php

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    protected static string | UnitEnum | null $navigationGroup = 'Master Data';
    protected static ?string $modelLabel = 'Shift';
    protected static ?string $pluralModelLabel = 'Shift';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama Shift')->required()->maxLength(80),
            TimePicker::make('start_time')->label('Jam Masuk')->seconds(false)->required(),
            TimePicker::make('end_time')->label('Jam Pulang')->seconds(false)->required(),
            TextInput::make('late_tolerance_min')->label('Toleransi Terlambat')
                ->numeric()->default(0)->minValue(0)->maxValue(120)->suffix('menit')->required(),
            Toggle::make('is_overnight')->label('Shift Lintas Hari (malam)')
                ->helperText('Aktifkan jika jam pulang jatuh di hari berikutnya, misalnya 22:00 - 06:00.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Shift')->searchable(),
                TextColumn::make('start_time')->label('Masuk')->time('H:i'),
                TextColumn::make('end_time')->label('Pulang')->time('H:i'),
                TextColumn::make('late_tolerance_min')->label('Toleransi')->suffix(' mnt'),
                IconColumn::make('is_overnight')->label('Lintas Hari')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageShifts::route('/')];
    }
}