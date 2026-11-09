<?php

namespace App\Http\Controllers;

use App\Models\FaceTemplate;
use Illuminate\Support\Facades\Storage;

class FacePhotoController extends Controller
{
    public function show(FaceTemplate $faceTemplate)
    {
        abort_unless(Storage::disk('public')->exists($faceTemplate->photo_path), 404);

        activity()->causedBy(auth()->user())->performedOn($faceTemplate)->log('Melihat foto wajah');

        return Storage::disk('public')->response($faceTemplate->photo_path);
    }
}