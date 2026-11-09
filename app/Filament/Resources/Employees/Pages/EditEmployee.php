<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    // karyawan tidak dihapus; ubah statusnya menjadi Nonaktif/Resign
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record->user;

        $data['email'] = $user?->email;
        $data['role']  = $user?->roles->first()?->name ?? 'karyawan';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $userData = [
                'name'      => $data['name'],
                'email'     => $data['email'],
                'is_active' => $data['status'] === 'aktif',
            ];

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
                $userData['must_change_password'] = false;
            }

            $record->user?->update($userData);

            if (! empty($data['role']) && auth()->user()->hasAnyRole(['super_admin', 'hrd'])) {
                $record->user?->syncRoles([$data['role']]);
            }

            unset($data['email'], $data['password'], $data['role']);
            $record->update($data);

            return $record;
        });
    }
}