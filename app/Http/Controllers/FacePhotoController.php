<?php

namespace App\Http\Controllers;

use App\Models\FaceTemplate;
use Illuminate\Support\Facades\Storage;

class FacePhotoController extends Controller
{
    public function show(FaceTemplate $faceTemplate)
    {
        $path = $faceTemplate->photo_path;
        $disk = Storage::disk('public');

        abort_if(! $path || ! $disk->exists($path), 404, 'File foto tidak ditemukan: ' . $path);

        // buang keluaran liar (spasi/BOM/notice) supaya data gambar tidak rusak
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response()->file($disk->path($path), [
            'Content-Type'  => $disk->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}