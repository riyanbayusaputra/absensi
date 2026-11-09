<?php

namespace App\Livewire\Employee;

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.mobile')]
class Profile extends Component
{
    use WithFileUploads;

    public $photo = null;
    public string $phone = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $notice = '';

    public function mount(): void
    {
        $this->phone = (string) (auth()->user()->employee?->phone ?? '');
    }

    public function savePhoto(): void
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403);

        $this->resetErrorBag();
        $this->notice = '';

        $this->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'photo.required' => 'Pilih foto terlebih dahulu.',
            'photo.image'    => 'File harus berupa gambar.',
            'photo.mimes'    => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'photo.max'      => 'Ukuran foto maksimal 2 MB.',
        ]);

        $path = $this->photo->store('employees', 'public');

        if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
            Storage::disk('public')->delete($employee->photo);
        }

        $employee->update(['photo' => $path]);
        $this->reset('photo');
        $this->notice = 'Foto profil diperbarui.';
    }

    public function removePhoto(): void
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403);

        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
            $employee->update(['photo' => null]);
        }

        $this->notice = 'Foto profil dihapus.';
    }

    public function savePhone(): void
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 403);

        $this->notice = '';

        $this->validate([
            'phone' => ['nullable', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
        ], [
            'phone.regex' => 'Nomor HP tidak valid (8 sampai 20 digit, boleh diawali +).',
        ]);

        $employee->update(['phone' => $this->phone !== '' ? $this->phone : null]);
        $this->notice = 'Nomor HP disimpan.';
    }

    public function changePassword()
    {
        $this->notice = '';

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => [
                'required', 'min:8', 'confirmed', 'different:current_password',
                function ($attribute, $value, $fail) {
                    if (! preg_match('/[A-Za-z]/', $value) || ! preg_match('/\d/', $value)) {
                        $fail('Password harus mengandung huruf dan angka.');
                    }
                },
            ],
        ], [
            'current_password.required'         => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini salah.',
            'password.required'                 => 'Password baru wajib diisi.',
            'password.min'                      => 'Password baru minimal 8 karakter.',
            'password.confirmed'                => 'Konfirmasi password tidak sama.',
            'password.different'                => 'Password baru harus berbeda dari yang lama.',
        ]);

        $user = auth()->user();
        $user->update([
            'password'             => $this->password,   // di-hash otomatis oleh cast 'hashed'
            'must_change_password' => false,
        ]);

        activity()->causedBy($user)->log('Password diganti oleh pengguna');
        session()->regenerate();

        return $this->redirectRoute('app.home');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.employee.profile', [
            'user'     => $user,
            'employee' => $user->employee?->load('department', 'position', 'manager', 'shift'),
           'forced'   => false,
        ]);
    }
}