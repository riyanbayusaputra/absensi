<?php

namespace App\Filament\Resources\Holidays;

use App\Filament\Resources\Holidays\Pages\ManageHolidays;
use App\Models\Holiday;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string | UnitEnum | null $navigationGroup = 'Master Data';
    protected static ?string $modelLabel = 'Hari Libur';
    protected static ?string $pluralModelLabel = 'Hari Libur';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->label('Tanggal')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->label('Keterangan')->required()->maxLength(150),
            Toggle::make('is_collective_leave')->label('Cuti Bersama'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('d M Y (l)')->sortable(),
                TextColumn::make('name')->label('Keterangan')->searchable(),
                IconColumn::make('is_collective_leave')->label('Cuti Bersama')->boolean(),
            ])
            ->filters([
                SelectFilter::make('year')->label('Tahun')
                    ->options(fn () => collect(range(now()->year - 1, now()->year + 1))
                        ->mapWithKeys(fn ($y) => [$y => (string) $y])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereYear('date', $data['value'])
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageHolidays::route('/')];
    }
}