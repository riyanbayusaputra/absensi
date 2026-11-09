<?php

namespace App\Filament\Resources\LeaveBalances\Pages;

use App\Filament\Resources\LeaveBalances\LeaveBalanceResource;
use App\Services\LeaveService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveBalances extends ManageRecords
{
    protected static string $resource = LeaveBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label('Buat saldo ' . now()->year)
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Membuat saldo tahun ini untuk semua karyawan aktif yang belum punya, termasuk carry-over.')
                ->visible(fn () => auth()->user()->hasAnyRole(['super_admin', 'hrd']))
                ->action(function () {
                    $n = app(LeaveService::class)->generateForAll(now()->year);
                    Notification::make()->title("Saldo diproses untuk {$n} karyawan")->success()->send();
                }),
        ];
    }
}