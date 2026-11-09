<?php

namespace App\Livewire\Employee;

use App\Services\FaceService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile')]
class FaceEnrollment extends Component
{
    public function save(array $descriptors, string $photo, bool $consent, FaceService $faces)
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403);

        $faces->enroll($employee, $descriptors, $photo, $consent);

        session()->flash('status', 'Foto wajah terkirim dan menunggu persetujuan HRD.');

        return $this->redirectRoute('app.face');   // reload penuh, sengaja tanpa navigate
    }

    public function render()
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403, 'Akun belum terhubung dengan data karyawan.');

        return view('livewire.employee.face-enrollment', [
            'approved' => $employee->approvedFaceTemplate,
            'latest'   => $employee->faceTemplates()->latest('id')->first(),
            'cfg'      => [
                'gallery'  => (bool) config('attendance.face.allow_gallery'),
                'modelUrl' => config('attendance.face.model_url'),
            ],
        ]);
    }
}