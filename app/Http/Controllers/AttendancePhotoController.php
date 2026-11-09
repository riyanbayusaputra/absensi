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
        $disk = Storage::disk('public');

        abort_if(! $path || ! $disk->exists($path), 404, 'File foto tidak ditemukan: ' . $path);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response()->file($disk->path($path), [
            'Content-Type'  => $disk->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}