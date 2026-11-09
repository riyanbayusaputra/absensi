<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FaceTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FaceService
{
    /* ---------- Utilitas (dipakai juga di Step 4) ---------- */

    public static function distance(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $v) {
            $d = (float) $v - (float) $b[$i];
            $sum += $d * $d;
        }
        return sqrt($sum);
    }

    /** Jarak terkecil antara probe dan kumpulan descriptor template. */
    public static function bestDistance(array $probe, array $templates): float
    {
        return min(array_map(fn ($t) => self::distance($probe, $t), $templates));
    }

    public static function cleanDescriptor(mixed $d): ?array
    {
        if (! is_array($d) || count($d) !== 128) return null;

        $row = [];
        foreach ($d as $x) {
            if (! is_numeric($x) || ! is_finite((float) $x)) return null;
            $row[] = (float) $x;
        }
        return $row;
    }

    public static function decodeJpegDataUrl(string $dataUrl, int $maxBytes = 600 * 1024): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image/jpeg;base64,')) return null;

        $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($bin === false || strlen($bin) > $maxBytes) return null;

        $info = @getimagesizefromstring($bin);
        return ($info && $info['mime'] === 'image/jpeg') ? $bin : null;
    }

    /* ---------- Registrasi ---------- */

    public function enroll(Employee $employee, array $descriptors, string $photo, bool $consent): FaceTemplate
    {
        $cfg = config('attendance.face');

        if (! $consent) {
            $this->fail('Persetujuan pemrosesan data wajah wajib diberikan.');
        }
        if ($employee->faceTemplates()->where('status', 'pending')->exists()) {
            $this->fail('Registrasi sebelumnya masih menunggu persetujuan HRD.');
        }

        $n = count($descriptors);
        if ($n < $cfg['min_samples'] || $n > $cfg['max_samples']) {
            $this->fail('Jumlah sampel wajah tidak valid.');
        }

        $clean = [];
        foreach ($descriptors as $d) {
            $row = self::cleanDescriptor($d) ?? $this->fail('Format data wajah tidak valid.');
            $clean[] = array_map(fn ($x) => round($x, 6), $row);
        }

        // jika lebih dari 1 sampel, semuanya harus berasal dari wajah yang sama
        foreach ($clean as $i => $d) {
            if ($i > 0 && self::distance($clean[0], $d) > $cfg['enroll_max_distance']) {
                $this->fail('Sampel wajah tidak konsisten. Ulangi di tempat dengan cahaya cukup.');
            }
        }

        $bin = self::decodeJpegDataUrl($photo, 500 * 1024) ?? $this->fail('Foto tidak valid.');

        $path = "face/{$employee->id}/" . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, $bin);

        $auto = $cfg['auto_approve_first'] && ! $employee->approvedFaceTemplate;

        return FaceTemplate::create([
            'employee_id'  => $employee->id,
            'descriptors'  => $clean,
            'photo_path'   => $path,
            'status'       => $auto ? 'approved' : 'pending',
            'consented_at' => now(),
            'reviewed_at'  => $auto ? now() : null,
        ]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['face' => $message]);
    }
}