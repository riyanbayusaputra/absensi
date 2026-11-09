<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';
    protected static ?string $modelLabel = 'Karyawan';
    protected static ?string $pluralModelLabel = 'Karyawan';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['department', 'position', 'manager']);
        $user  = auth()->user();

        // atasan hanya melihat anggota timnya
        if ($user->hasRole('atasan') && ! $user->hasAnyRole(['super_admin', 'hrd'])) {
            $query->where('manager_id', $user->employee?->id ?? 0);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Karyawan')->tabs([

                Tab::make('Data Pribadi')->schema([
                    FileUpload::make('photo')->label('Foto Profil')
                        ->image()->avatar()
                        ->disk('public')->visibility('public')->directory('employees')
                        ->maxSize(2048)->imageEditor()->columnSpanFull(),
                    TextInput::make('nik')->label('NIK')
                        ->required()->unique(ignoreRecord: true)->maxLength(30),
                    TextInput::make('name')->label('Nama Lengkap')->required()->maxLength(120),
                    TextInput::make('phone')->label('No. HP')->tel()->maxLength(20),
                    DatePicker::make('join_date')->label('Tanggal Masuk'),
                    Select::make('status')->label('Status')
                        ->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'resign' => 'Resign'])
                        ->default('aktif')->required(),
                    TextColumn::make('face_status')->label('Wajah')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Terdaftar'            => 'success',
                        'Menunggu persetujuan' => 'warning',
                        default                => 'gray',
                    }),
                ])->columns(2),

                Tab::make('Pekerjaan')->schema([
                    Select::make('department_id')->label('Departemen')
                        ->relationship('department', 'name')->searchable()->preload()
                        ->createOptionForm([TextInput::make('name')->label('Nama')->required()]),
                    Select::make('position_id')->label('Jabatan')
                        ->relationship('position', 'name')->searchable()->preload()
                        ->createOptionForm([TextInput::make('name')->label('Nama')->required()]),
                    Select::make('manager_id')->label('Atasan Langsung')
                        ->relationship('manager', 'name', fn (Builder $query) => $query->where('status', 'aktif'), ignoreRecord: true)
                        ->searchable()->preload(),
                    Select::make('default_shift_id')->label('Shift Default')
                        ->relationship('shift', 'name')->preload(),
                    Select::make('locations')->label('Lokasi Absen yang Diizinkan')
                        ->relationship('locations', 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->multiple()->preload()->required()->columnSpanFull(),
                ])->columns(2),

                Tab::make('Akun Login')->schema([
                    TextInput::make('email')->label('Email')->email()->required()
                        ->unique('users', 'email', ignorable: fn (?Model $record) => $record?->user),
                    TextInput::make('password')->label('Password')
                        ->password()->revealable()->minLength(8)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Kosongkan jika tidak ingin mengubah password.'
                            : 'Karyawan akan diminta mengganti password saat login pertama.'),
                    Select::make('role')->label('Role')
                        ->options(['karyawan' => 'Karyawan', 'atasan' => 'Atasan', 'hrd' => 'HRD'])
                        ->default('karyawan')->required()
                        ->visible(fn () => auth()->user()->hasAnyRole(['super_admin', 'hrd'])),
                ])->columns(2),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo')->label('')->circular()->disk('public')
                    ->defaultImageUrl(fn (Employee $record) => 'https://ui-avatars.com/api/?background=e0e7ff&color=4f46e5&name=' . urlencode($record->name)),
                TextColumn::make('nik')->label('NIK')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('department.name')->label('Departemen')->sortable(),
                TextColumn::make('position.name')->label('Jabatan'),
                TextColumn::make('manager.name')->label('Atasan')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'aktif' => 'success', 'nonaktif' => 'warning', default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('Departemen')->relationship('department', 'name'),
                SelectFilter::make('status')->label('Status')
                    ->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'resign' => 'Resign']),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit'   => EditEmployee::route('/{record}/edit'),
        ];
    }
}