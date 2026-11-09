<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Support\Facades\Storage;

class AttendancePhotoController extends Controller
{
    public function show(Attendance $attendance, string $type)
    {
        abort_unless(in_array($type, ['in', 'out'], true), 404);

        $path = $attendance->{$type . '_photo'};
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}