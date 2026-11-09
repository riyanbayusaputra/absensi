<?php

namespace App\Filament\Resources\OfficeLocations;

use App\Filament\Resources\OfficeLocations\Pages\CreateOfficeLocation;
use App\Filament\Resources\OfficeLocations\Pages\EditOfficeLocation;
use App\Filament\Resources\OfficeLocations\Pages\ListOfficeLocations;
use App\Models\OfficeLocation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OfficeLocationResource extends Resource
{
    protected static ?string $model = OfficeLocation::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static string | UnitEnum | null $navigationGroup = 'Master Data';
    protected static ?string $modelLabel = 'Lokasi Kantor';
    protected static ?string $pluralModelLabel = 'Lokasi Kantor';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
           Section::make('Lokasi')->schema([
    TextInput::make('name')->label('Nama Lokasi')->required()->maxLength(100),
    Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),

    View::make('filament.forms.location-map')->columnSpanFull(),

    TextInput::make('latitude')->label('Latitude')
        ->numeric()->required()->minValue(-90)->maxValue(90)->live(onBlur: true),
    TextInput::make('longitude')->label('Longitude')
        ->numeric()->required()->minValue(-180)->maxValue(180)->live(onBlur: true),
    TextInput::make('radius_m')->label('Radius absen')
        ->numeric()->required()->default(100)->minValue(10)->maxValue(5000)->suffix('meter')->live(onBlur: true)
        ->helperText('Sarankan 50 sampai 100 m. Terlalu kecil membuat karyawan sah sering ditolak karena GPS bergeser.'),
])->columns(2)->columnSpanFull(),
        
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('latitude'),
                TextColumn::make('longitude'),
                TextColumn::make('radius_m')->label('Radius')->suffix(' m'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('employees_count')->label('Karyawan')->counts('employees'),
            ])
            ->recordActions([
                Action::make('maps')->label('Peta')->icon('heroicon-o-map')
                    ->url(fn (OfficeLocation $record) => "https://www.google.com/maps?q={$record->latitude},{$record->longitude}")
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListOfficeLocations::route('/'),
            'create' => CreateOfficeLocation::route('/create'),
            'edit'   => EditOfficeLocation::route('/{record}/edit'),
        ];
    }
}