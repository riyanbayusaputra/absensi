
<?php

use App\Http\Controllers\FacePhotoController;
use App\Http\Controllers\AttendancePhotoController;
use App\Livewire\Auth\Login;
use App\Livewire\Employee\FaceEnrollment;
use App\Livewire\Employee\Home;
use App\Livewire\Employee\Attend;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeaveAttachmentController;
use App\Livewire\Employee\Leave\Create as LeaveCreate;
use App\Livewire\Employee\Leave\Index as LeaveIndex;
use App\Livewire\Employee\History;
use App\Livewire\Employee\Profile;

Route::redirect('/', '/app');

Route::get('/login', Login::class)
    ->name('login')
    ->middleware('guest');

// Halaman karyawan
Route::middleware(['auth', 'role:karyawan|atasan|hrd|super_admin'])
    ->prefix('app')
    ->group(function () {
        Route::get('/', Home::class)->name('app.home');

        Route::get('/wajah', FaceEnrollment::class)
            ->name('app.face');

        Route::get('/absen', Attend::class)
            ->name('app.attend');
        Route::get('/cuti', LeaveIndex::class)->name('app.leave');
        Route::get('/cuti/ajukan', LeaveCreate::class)->name('app.leave.create');
     

        Route::get('/riwayat', History::class)->name('app.history');
        Route::get('/profil', Profile::class)->name('app.profile');

        // Route berikutnya:
        // Riwayat, cuti, profil
    });

// Foto registrasi wajah
Route::get('/files/face/{faceTemplate}', [
    FacePhotoController::class,
    'show'
])
    ->name('face.photo')
    ->middleware(['auth', 'role:super_admin|hrd']);

// Foto absensi
Route::get('/files/attendance/{attendance}/{type}', [AttendancePhotoController::class, 'show'])
    ->name('attendance.photo')
    ->middleware(['auth', 'role:super_admin|hrd']);

Route::get('/files/leave/{leaveRequest}', [LeaveAttachmentController::class, 'show'])
    ->name('leave.attachment')->middleware('auth');

// Logout
Route::post('/logout', function () {
    auth()->logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');