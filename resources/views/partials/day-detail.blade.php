
@php
    $a = $d['att'];
    $isPast = $d['date']->lt($today);
    $inWindow = $d['date']->gte($today->copy()->subDays($backdate));
@endphp

<div class="space-y-2">

    {{-- Informasi absensi --}}
    @if ($a)

        {{-- Informasi shift --}}
        @if ($a->shift)
            <p class="text-xs text-slate-500">
                Shift {{ $a->shift->name }}
                ({{ substr($a->shift->start_time, 0, 5) }}&ndash;{{ substr($a->shift->end_time, 0, 5) }})
            </p>
        @endif

        {{-- Jam masuk, pulang, dan durasi --}}
        <div class="grid grid-cols-3 gap-2 text-sm">
            <div>
                <p class="text-[11px] text-slate-400">Masuk</p>
                <p class="font-semibold">
                    {{ $a->check_in_at?->format('H:i') ?? '-' }}
                </p>
            </div>

            <div>
                <p class="text-[11px] text-slate-400">Pulang</p>
                <p class="font-semibold">
                    {{ $a->check_out_at?->format('H:i') ?? '-' }}
                </p>
            </div>

            <div>
                <p class="text-[11px] text-slate-400">Durasi</p>
                <p class="font-semibold">
                    @if ($a->work_minutes)
                        {{ intdiv($a->work_minutes, 60) }}j
                        {{ $a->work_minutes % 60 }}m
                    @else
                        -
                    @endif
                </p>
            </div>
        </div>

        {{-- Detail keterlambatan --}}
        <div class="space-y-0.5 text-xs text-slate-600">

            @if ($a->late_minutes)
                <p>
                    Terlambat {{ $a->late_minutes }} menit
                </p>
            @endif

            @if ($a->early_leave_minutes)
                <p>
                    Pulang lebih awal {{ $a->early_leave_minutes }} menit
                </p>
            @endif

            @if ($a->inLocation)
                <p>
                    Lokasi: {{ $a->inLocation->name }}

                    @if ($a->in_distance_m)
                        ({{ $a->in_distance_m }} m dari titik)
                    @endif
                </p>
            @endif

        </div>
    @endif

    {{-- Informasi tambahan --}}
    <div class="space-y-1 text-xs">

        {{-- Hari libur --}}
        @if ($d['holiday'])
            <p class="rounded-lg bg-slate-100 p-2 text-slate-600">
                Hari libur: {{ $d['holiday'] }}
            </p>
        @endif
        @if ($d['code'] === 'L' && ! $d['holiday'])
            <p class="rounded-lg bg-slate-100 p-2 text-slate-600">Libur sesuai jadwal Anda.</p>
        @endif
        @if (($d['off'] ?? false) && $a && ! $d['holiday'])
            <p class="rounded-lg bg-amber-50 p-2 text-amber-800">Anda masuk pada hari libur jadwal.</p>
        @endif

        {{-- Cuti atau izin --}}
        @if ($d['leave'])
            <p class="rounded-lg bg-blue-50 p-2 text-blue-800">
                {{ $d['leave']['name'] }}

                @if ($d['leave']['half'])
                    (setengah hari)
                @endif

                @if ($d['leave']['reason'])
                    &mdash; {{ $d['leave']['reason'] }}
                @endif
            </p>
        @endif

        {{-- Absensi ditolak HRD --}}
        @if ($d['rejected'])
            <p class="rounded-lg bg-red-50 p-2 text-red-700">
                Absen ini dinyatakan tidak valid oleh HRD.

                @if ($a?->review_note)
                    {{ $a->review_note }}
                @endif
            </p>
        @endif

        {{-- Data dikoreksi --}}
        @if ($d['corrected'])
            <p class="rounded-lg bg-sky-50 p-2 text-sky-700">
                Data ini sudah dikoreksi.
            </p>
        @endif

        {{-- Sedang bekerja --}}
        @if ($d['working'])
            <p class="rounded-lg bg-green-50 p-2 text-green-700">
                Sedang bekerja, belum absen pulang.
            </p>
        @endif

        {{-- Belum absen pulang --}}
        @if ($d['no_out'])
            <p class="rounded-lg bg-amber-50 p-2 text-amber-800">
                Belum absen pulang.
            </p>
        @endif

        {{-- Tidak hadir --}}
        @if ($d['code'] === 'A' && ! $d['rejected'])
            <p class="rounded-lg bg-red-50 p-2 text-red-700">
                Tidak ada absensi, cuti, atau izin pada tanggal ini.
            </p>
        @endif

    </div>

    {{-- Tombol pengajuan koreksi --}}
    @if (
        Route::has('app.correction.create')
        && $isPast
        && $inWindow
        && ! in_array($d['code'], ['L', 'C', 'S', 'I', 'K'], true)
    )
        <a
            wire:navigate
            href="{{ route('app.correction.create', [
                'date' => $d['date']->toDateString()
            ]) }}"
            class="block rounded-xl border border-indigo-200 py-2 text-center text-sm font-semibold text-indigo-600 hover:bg-indigo-50 transition"
        >
            Ajukan koreksi absensi
        </a>
    @endif

</div>