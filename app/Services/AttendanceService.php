<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceService
{
    public function __construct(private LocationService $locations) {}

    /* ---------- Status halaman ---------- */

    public function readiness(Employee $e): ?array
    {
        if ($e->status !== 'aktif' || ! $e->user?->is_active) {
            return ['reason' => 'Akun Anda tidak aktif.', 'cta' => null];
        }
        if (! $e->approvedFaceTemplate) {
            return ['reason' => 'Wajah Anda belum terdaftar atau belum disetujui HRD.', 'cta' => route('app.face')];
        }
        if (! $e->shift) {
            return ['reason' => 'Shift Anda belum diatur. Hubungi HRD.', 'cta' => null];
        }
        if (! $e->locations()->where('is_active', true)->exists()) {
            return ['reason' => 'Lokasi absen Anda belum diatur. Hubungi HRD.', 'cta' => null];
        }
        if ($leave = app(LeaveService::class)->onLeave($e)) {
    return ['reason' => "Hari ini Anda berstatus {$leave->type->name}, absen tidak diperlukan.", 'cta' => null];
}
        return null;
    }

    public function nextAction(Employee $e): array
    {
        if ($r = $this->readiness($e)) {
            return ['type' => 'blocked'] + $r;
        }

        $now = now();
        if ($open = $this->findOpen($e, $e->shift, $now)) {
            return ['type' => 'out', 'attendance' => $open];
        }

        $today = Attendance::where('employee_id', $e->id)->whereDate('date', $now->toDateString())->first();
        if ($today?->check_out_at) {
            return ['type' => 'done', 'attendance' => $today];
        }

        if ($msg = $this->checkInBlock($e->shift, $now)) {
            return ['type' => 'blocked', 'reason' => $msg, 'cta' => null];
        }

        return ['type' => 'in', 'attendance' => null];
    }

    /* ---------- Rekam absen ---------- */

    public function record(Employee $employee, array $p): array
    {
        if ($r = $this->readiness($employee)) {
            throw new AttendanceException($r['reason']);
        }

        $cfg = config('attendance');
        $key = 'face-fail:' . $employee->id;

        if (RateLimiter::tooManyAttempts($key, $cfg['rules']['max_failed_face'])) {
            $m = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw new AttendanceException("Terlalu banyak percobaan wajah gagal. Coba lagi dalam {$m} menit atau hubungi HRD.");
        }

        // 1. Validasi payload
        $lat = $this->num($p, 'lat', -90, 90);
        $lng = $this->num($p, 'lng', -180, 180);
        $acc = $this->num($p, 'accuracy', 0, 100000);

        if (is_numeric($p['gps_ts'] ?? null) && is_numeric($p['client_ts'] ?? null)
            && abs($p['client_ts'] - $p['gps_ts']) > 60000) {
            throw new AttendanceException('Data lokasi kedaluwarsa. Ulangi proses absen.');
        }

        $descriptor = FaceService::cleanDescriptor($p['descriptor'] ?? null)
            ?? throw new AttendanceException('Data wajah tidak valid. Ulangi proses absen.');
        $photo = FaceService::decodeJpegDataUrl((string) ($p['photo'] ?? ''))
            ?? throw new AttendanceException('Foto tidak valid. Ulangi proses absen.');

    

        // 2. Lokasi (geofencing)
        if ($acc > $cfg['rules']['max_accuracy_m']) {
            throw new AttendanceException('Akurasi GPS kurang baik (' . round($acc) . ' m). Pindah ke area terbuka lalu coba lagi.');
        }
        [$loc, $dist] = $this->locations->nearestAllowed($employee, $lat, $lng);
        if ($dist > $loc->radius_m) {
            throw new AttendanceException("Anda berada ± {$dist} m dari {$loc->name} (batas {$loc->radius_m} m).");
        }

        // 3. Wajah
        $score = FaceService::bestDistance($descriptor, $employee->approvedFaceTemplate->descriptors);
        if ($score > $cfg['face']['match_threshold']) {
            RateLimiter::hit($key, $cfg['rules']['lock_minutes'] * 60);
            $left = RateLimiter::remaining($key, $cfg['rules']['max_failed_face']);
            throw new AttendanceException("Wajah tidak cocok. Sisa percobaan: {$left}.");
        }
        RateLimiter::clear($key);

        // 4. Simpan
        return DB::transaction(fn () => $this->persist($employee, [
            'lat' => $lat, 'lng' => $lng, 'acc' => $acc, 'dist' => $dist,
            'loc' => $loc, 'score' => $score, 'photo' => $photo,
        ]));
    }

    private function persist(Employee $employee, array $d): array
    {
        $now   = now();
        $shift = $employee->shift;
        $open  = $this->findOpen($employee, $shift, $now, lock: true);

        // ---- Absen PULANG
        if ($open) {
            if ($open->check_in_at->diffInMinutes($now) < config('attendance.rules.min_gap_minutes')) {
                throw new AttendanceException('Anda baru saja absen masuk. Tunggu sebentar.');
            }

            [, $end] = $this->shiftTimes($shift, $open->date);
            $early = $now->lt($end) ? (int) floor($now->diffInMinutes($end)) : 0;
            $work  = (int) floor($open->check_in_at->diffInMinutes($now));

            $open->update([
                'check_out_at'        => $now,
                'out_location_id'     => $d['loc']->id,
                'out_lat'             => $d['lat'],
                'out_lng'             => $d['lng'],
                'out_accuracy'        => $d['acc'],
                'out_distance_m'      => $d['dist'],
                'out_photo'           => $this->storePhoto($employee, $open->date, 'out', $d['photo']),
                'out_face_score'      => $d['score'],
                'early_leave_minutes' => $early,
                'work_minutes'        => $work,
            ]);

            return [
                'type'     => 'out',
                'time'     => $now->format('H:i'),
                'status'   => ($early > 0 ? "Pulang cepat {$early} menit" : 'Pulang tepat waktu')
                              . ' · Durasi ' . intdiv($work, 60) . ' jam ' . ($work % 60) . ' menit',
                'location' => $d['loc']->name,
                'distance' => $d['dist'],
            ];
        }

        // ---- Absen MASUK
        $exists = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $now->toDateString())->lockForUpdate()->first();
        if ($exists) {
            throw new AttendanceException('Absen hari ini sudah lengkap.');
        }
        if ($msg = $this->checkInBlock($shift, $now)) {
            throw new AttendanceException($msg);
        }

        [$start] = $this->shiftTimes($shift, $now);
        $limit = $start->copy()->addMinutes((int) $shift->late_tolerance_min);
        $late  = $now->gt($limit) ? (int) floor($start->diffInMinutes($now)) : 0;

        Attendance::create([
            'employee_id'    => $employee->id,
            'date'           => $now->toDateString(),
            'shift_id'       => $shift->id,
            'check_in_at'    => $now,
            'in_location_id' => $d['loc']->id,
            'in_lat'         => $d['lat'],
            'in_lng'         => $d['lng'],
            'in_accuracy'    => $d['acc'],
            'in_distance_m'  => $d['dist'],
            'in_photo'       => $this->storePhoto($employee, $now, 'in', $d['photo']),
            'in_face_score'  => $d['score'],
            'status'         => $late > 0 ? 'terlambat' : 'tepat_waktu',
            'late_minutes'   => $late,
            'ip_address'     => request()->ip(),
            'user_agent'     => mb_substr((string) request()->userAgent(), 0, 500),
        ]);

        return [
            'type'     => 'in',
            'time'     => $now->format('H:i'),
            'status'   => $late > 0 ? "Terlambat {$late} menit" : 'Tepat waktu',
            'location' => $d['loc']->name,
            'distance' => $d['dist'],
        ];
    }

    /* ---------- Helper ---------- */

    private function shiftTimes(Shift $shift, Carbon $date): array
    {
        $day   = $date->toDateString();
        $start = Carbon::parse("{$day} {$shift->start_time}");
        $end   = Carbon::parse("{$day} {$shift->end_time}");

        if ($shift->is_overnight || $end->lte($start)) {
            $end->addDay();
        }
        return [$start, $end];
    }

    private function checkInBlock(Shift $shift, Carbon $now): ?string
    {
        [$start, $end] = $this->shiftTimes($shift, $now);
        $opensAt = $start->copy()->subMinutes(config('attendance.rules.checkin_open_before_min'));

        if ($now->lt($opensAt)) {
            return 'Absen masuk dibuka pukul ' . $opensAt->format('H:i') . '.';
        }
        if (! $shift->is_overnight && $now->gt($end)) {
            return 'Jam kerja hari ini sudah berakhir. Ajukan koreksi absensi jika perlu.';
        }
        return null;
    }

    private function findOpen(Employee $e, Shift $shift, Carbon $now, bool $lock = false): ?Attendance
    {
        $q = Attendance::where('employee_id', $e->id)
            ->whereNotNull('check_in_at')->whereNull('check_out_at')
            ->where('check_in_at', '>=', $now->copy()->subHours(18))
            ->latest('check_in_at');
        if ($lock) $q->lockForUpdate();

        $open = $q->first();

        // shift biasa: absen masuk kemarin yang tidak pulang tidak memblokir hari ini
        if ($open && ! $shift->is_overnight && ! $open->date->isSameDay($now)) {
            return null;
        }
        return $open;
    }

    private function storePhoto(Employee $e, Carbon $date, string $type, string $bin): string
    {
        $path = "attendance/{$e->id}/{$date->format('Y-m-d')}/{$type}-" . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, $bin);

        return $path;
    }

    private function num(array $p, string $k, float $min, float $max): float
    {
        $v = $p[$k] ?? null;
        if (! is_numeric($v) || $v < $min || $v > $max) {
            throw new AttendanceException('Data lokasi tidak valid. Muat ulang halaman.');
        }
        return (float) $v;
    }
}