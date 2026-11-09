<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ManageLeaveRequests extends ManageRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'tindakan' => Tab::make('Perlu tindakan saya')
                ->modifyQueryUsing(function ($query) {
                    $user = auth()->user();

                    if ($user->hasRole('super_admin')) {
                        return $query->where('status', 'pending');
                    }

                    return $query->where(function ($w) use ($user) {
                        if ($user->hasRole('hrd')) {
                            $w->orWhere(fn ($x) => $x->awaiting('hrd'));
                        }
                        if ($user->hasRole('atasan')) {
                            $w->orWhere(fn ($x) => $x->awaiting('atasan'));
                        }
                    });
                }),

            'semua' => Tab::make('Semua'),

            'disetujui' => Tab::make('Disetujui')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'approved')),
        ];
    }
}