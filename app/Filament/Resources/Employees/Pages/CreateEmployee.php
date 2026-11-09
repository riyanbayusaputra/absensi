<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'                 => $data['name'],
                'email'                => $data['email'],
                'password'             => $data['password'],   // di-hash otomatis oleh cast 'hashed'
                'is_active'            => $data['status'] === 'aktif',
                'must_change_password' => true,
            ]);
            $user->assignRole($data['role'] ?? 'karyawan');

            unset($data['email'], $data['password'], $data['role']);
            $data['user_id'] = $user->id;

            return static::getModel()::create($data);
        });
    }
}