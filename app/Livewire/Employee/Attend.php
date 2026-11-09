<?php

namespace App\Livewire\Employee;

use App\Exceptions\AttendanceException;
use App\Services\AttendanceService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile')]
class Attend extends Component
{
    public function submit(array $payload, AttendanceService $svc): array
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403);

        $this->skipRender();

        try {
            $result = $svc->record($employee, $payload);

            return ['ok' => true] + $result;
        } catch (AttendanceException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function render(AttendanceService $svc)
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403, 'Akun belum terhubung dengan data karyawan.');

        $next = $svc->nextAction($employee);

        $locations = $employee->locations()->where('is_active', true)
            ->get(['office_locations.id', 'office_locations.name', 'office_locations.latitude',
                   'office_locations.longitude', 'office_locations.radius_m'])
            ->map(fn ($l) => [
                'name'   => $l->name,
                'lat'    => (float) $l->latitude,
                'lng'    => (float) $l->longitude,
                'radius' => (int) $l->radius_m,
            ])->values();

        return view('livewire.employee.attend', [
            'next'     => $next,
            'employee' => $employee,
            'scanCfg'  => [
                'type'        => $next['type'],
                                'maxAccuracy' => config('attendance.rules.max_accuracy_m'),
                'locations'   => $locations,
                'homeUrl'     => route('app.home'),
                'modelUrl'    => config('attendance.face.model_url'),
            ],
        ]);
    }
}