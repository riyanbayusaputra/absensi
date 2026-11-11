@php
    $r   = fn (string $name) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';
    $has = fn (string $name) => \Illuminate\Support\Facades\Route::has($name);

    $type    = $next['type'] ?? null;
    $att     = $next['attendance'] ?? null;
    $late    = ($att?->status ?? null) === 'terlambat';
    $punched = in_array($type, ['out', 'done'], true);
    $dur     = fn (int $m) => intdiv($m, 60) . 'j ' . ($m % 60) . 'm';
    $sinceMs = ($type === 'out' && $att?->check_in_at) ? $att->check_in_at->getTimestamp() * 1000 : 'null';

    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

    $shiftText = $employee?->shift
        ? 'Shift ' . $employee->shift->name . ', ' . substr($employee->shift->start_time, 0, 5) . '–' . substr($employee->shift->end_time, 0, 5)
        : null;

    // cap karet pada kartu: [teks, warna]
    $stamp = match (true) {
        $punched && $late           => ['Terlambat', 'text-amber-300 border-amber-300'],
        $punched                    => ['Tepat waktu', 'text-brand-200 border-brand-200'],
        $type === 'in' && $todayOff => [$todayHoliday ? 'Libur nasional' : 'Libur jadwal', 'text-slate-300 border-slate-300'],
        $type === 'in'              => ['Belum absen', 'text-amber-300 border-amber-300'],
        default                     => null,
    };

    $icons = [
        'bell'     => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
        'pin'      => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
        'clock'    => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
        'moon'     => 'M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z',
        'edit'     => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
        'face'     => 'M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75z',
        'chev'     => 'M8.25 4.5l7.5 7.5-7.5 7.5',
    ];
    $svg = fn (string $k, string $c = 'h-5 w-5') =>
        '<svg class="' . $c . '" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $icons[$k] . '"/></svg>';

    $focus = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';

    // jadwal minggu ini
    $offNames = collect($week ?? [])->filter(fn ($d) => $d['off'] && ! $d['holiday'])
        ->map(fn ($d) => $d['date']->translatedFormat('l'))->values()->all();
    $holNames = collect($week ?? [])->filter(fn ($d) => (bool) $d['holiday'])
        ->map(fn ($d) => $d['holiday'])->values()->all();

    // ringkasan bulan
    $sum = $summary ?? null;
    if ($sum) {
        $seg = [
            ['Tepat waktu', $sum['tepat'], 'bg-brand-600'],
            ['Terlambat', $sum['terlambat'], 'bg-amber-400'],
            ['Alfa', $sum['alfa'], 'bg-red-500'],
            ['Cuti dan izin', $sum['leave'], 'bg-sky-500'],
        ];
        $total = max(1, $sum['hari_kerja'], $sum['tepat'] + $sum['terlambat'] + $sum['alfa'] + $sum['leave']);
    }

    $menu = collect([
        ['Riwayat absensi', 'Daftar dan kalender', 'app.history', true, 'clock'],
        ['Cuti dan izin', $annual ? 'Sisa cuti tahunan ' . ($annual->remaining + 0) . ' hari' : 'Ajukan cuti atau izin', 'app.leave', true, 'calendar'],
        ['Jadwal saya', 'Masuk dan libur tiap minggu', 'app.schedule', true, 'calendar'],
        ['Lembur', 'Ajukan dan pantau lembur', 'app.overtime', true, 'moon'],
        ['Koreksi absensi', 'Lupa absen atau jam salah', 'app.correction', true, 'edit'],
        ['Registrasi wajah', $employee?->face_status ?? 'Akun belum terhubung data karyawan', 'app.face', false, 'face'],
    ])->filter(fn ($m) => $has($m[2]));
@endphp

<div class="px-5 pb-8 pt-6"

     x-data="{
        off: {{ $serverMs }} - Date.now(),
        tz: '{{ $tz }}',
        now: new Date(),
        since: {{ $sinceMs }},
        showNotif: {{ $notifs->isNotEmpty() ? 'true' : 'false' }},
        t: null,
        init() { this.tick(); this.t = setInterval(() => this.tick(), 1000); },
        tick() { this.now = new Date(Date.now() + this.off); },
        destroy() { clearInterval(this.t); },
        parts() { const p = new Intl.DateTimeFormat('en-GB', { timeZone: this.tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(this.now); const g = (k) => (p.find((x) => x.type === k) || {}).value || '00'; return { h: g('hour'), m: g('minute'), s: g('second') }; },
        hm() { const p = this.parts(); return p.h + ':' + p.m; },
        sec() { return this.parts().s; },
        date() { return new Intl.DateTimeFormat('id-ID', { timeZone: this.tz, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(this.now); },
        greet() { const h = parseInt(this.parts().h, 10); return h < 11 ? 'Selamat pagi' : (h < 15 ? 'Selamat siang' : (h < 18 ? 'Selamat sore' : 'Selamat malam')); },
        worked() { if (!this.since) return ''; const m = Math.max(0, Math.floor((this.now.getTime() - this.since) / 60000)); return Math.floor(m / 60) + 'j ' + (m % 60) + 'm'; }
     }">

     

    {{-- ================= BAR ATAS ================= --}}
    <header class="flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ $r('app.profile') }}" aria-label="Buka profil" class="shrink-0 rounded-full {{ $focus }}">
                @if ($employee?->photo)
                    <img src="{{ asset('storage/' . $employee->photo) }}" alt="" class="h-10 w-10 rounded-full bg-slate-200 object-cover">
                @else
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ $initials }}</span>
                @endif
            </a>
            <div class="min-w-0">
                <p class="text-xs text-slate-500" x-text="greet()">Halo</p>
                <p class="truncate text-[15px] font-semibold text-slate-900">{{ $user->name }}</p>
            </div>
        </div>

        <button type="button" @click="showNotif = !showNotif" aria-label="Notifikasi"
                class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-700 hover:bg-slate-200 {{ $focus }}">
            {!! $svg('bell') !!}
            @if ($unread > 0)
                <span class="absolute right-0.5 top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unread }}</span>
            @endif
        </button>
    </header>

    {{-- ================= NOTIFIKASI ================= --}}
    @if ($notifs->isNotEmpty())
        <div x-show="showNotif" x-cloak class="mt-4 rounded-lg border border-slate-300 bg-white p-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold">Notifikasi</p>
                <button type="button" wire:click="markRead" class="text-xs font-semibold text-brand-700 hover:underline">Tandai sudah dibaca</button>
            </div>
            <ul class="mt-2 divide-y divide-slate-200">
                @foreach ($notifs as $n)
                    <li class="py-2">
                        <p class="text-sm font-medium">{{ $n->data['title'] ?? '' }}</p>
                        <p class="text-xs text-slate-500">{{ $n->data['body'] ?? '' }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ================= KARTU ABSEN ================= --}}
    <section class="mt-5" aria-label="Kartu absen hari ini">
        <div class="relative">

            {{-- bagian atas: jam --}}
            <div class="rounded-t-2xl border-b-[3px] border-dashed border-white/35 bg-brand-900 px-5 pb-6 pt-5 text-white">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm text-brand-200" x-text="date()">{{ now()->translatedFormat('l, j F Y') }}</p>
                    @if ($stamp)
                        <span class="-mt-0.5 shrink-0 -rotate-3 rounded-md border-[3px] border-double px-2.5 py-1 text-xs font-extrabold {{ $stamp[1] }}">{{ $stamp[0] }}</span>
                    @endif
                </div>

                <p class="mt-3 flex items-baseline gap-1.5 font-extrabold leading-none tracking-tight tabular-nums">
                    <span class="text-[60px]" x-text="hm()">{{ now()->format('H:i') }}</span>
                    <span class="text-2xl font-semibold text-brand-300" x-text="sec()">{{ now()->format('s') }}</span>
                </p>

                <p class="mt-3 text-sm text-brand-200">{{ $shiftText ?? 'Shift belum diatur' }}</p>
                <p class="mt-0.5 text-xs text-brand-300">Jam mengikuti server ({{ $tzLabel }})</p>
            </div>

            {{-- takik karcis --}}
            <div class="relative h-0" aria-hidden="true">
                <span class="absolute -left-2 -top-2 z-10 h-4 w-4 rounded-full bg-slate-50"></span>
                <span class="absolute -right-2 -top-2 z-10 h-4 w-4 rounded-full bg-slate-50"></span>
            </div>

            {{-- bagian bawah (stub) --}}
            <div class="rounded-b-2xl border border-t-0 border-slate-300 bg-white px-5 pb-5 pt-5">
                <dl class="grid grid-cols-3 divide-x divide-dashed divide-slate-300 text-center">
                    <div>
                        <dt class="text-xs text-slate-500">Masuk</dt>
                        <dd class="mt-1 text-xl font-bold tabular-nums {{ $att?->check_in_at ? 'text-slate-900' : 'text-slate-300' }}">{{ $att?->check_in_at?->format('H:i') ?? '--:--' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Pulang</dt>
                        <dd class="mt-1 text-xl font-bold tabular-nums {{ $att?->check_out_at ? 'text-slate-900' : 'text-slate-300' }}">{{ $att?->check_out_at?->format('H:i') ?? '--:--' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Durasi</dt>
                        @if ($type === 'out')
                            <dd class="mt-1 text-xl font-bold tabular-nums text-brand-700" x-text="worked()">0j 0m</dd>
                        @else
                            <dd class="mt-1 text-xl font-bold tabular-nums {{ $att?->work_minutes ? 'text-slate-900' : 'text-slate-300' }}">{{ $att?->work_minutes ? $dur($att->work_minutes) : '-' }}</dd>
                        @endif
                    </div>
                </dl>

                @if ($todayOff && $type === 'in')
                    <p class="mt-4 text-sm text-slate-600">
                        {{ $todayHoliday ? 'Hari ini libur: ' . $todayHoliday . '.' : 'Hari ini jadwal libur Anda.' }}
                        Absen tetap tercatat bila Anda masuk.
                    </p>
                @endif

                @if ($type === 'in')
                    <a href="{{ $r('app.attend') }}"
                       class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-brand-700 py-3.5 text-[15px] font-semibold text-white hover:bg-brand-800 {{ $focus }}">
                        {!! $svg('pin') !!} Absen masuk{{ $todayOff ? ' (hari libur)' : '' }}
                    </a>
                @elseif ($type === 'out')
                    <a href="{{ $r('app.attend') }}"
                       class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-slate-900 py-3.5 text-[15px] font-semibold text-white hover:bg-slate-800 {{ $focus }}">
                        {!! $svg('pin') !!} Absen pulang
                    </a>
                @elseif ($type === 'done')
                    <p class="mt-4 text-center text-sm font-medium text-brand-800">Absen hari ini sudah lengkap.</p>
                @else
                    <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                        <p>{{ $next['reason'] ?? 'Akun belum terhubung dengan data karyawan.' }}</p>
                        @if (! empty($next['cta']))
                            <a href="{{ $next['cta'] }}" class="mt-3 block rounded-lg bg-brand-700 py-2.5 text-center font-semibold text-white hover:bg-brand-800 {{ $focus }}">Daftarkan wajah</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ================= JADWAL MINGGU INI ================= --}}
    @if (! empty($week))
        <section class="mt-7">
            <div class="flex items-baseline justify-between">
                <h2 class="text-[15px] font-bold text-slate-900">Jadwal minggu ini</h2>
                @if ($has('app.schedule'))
                    <a wire:navigate href="{{ route('app.schedule') }}" class="text-xs font-semibold text-brand-700 hover:underline {{ $focus }}">Lihat detail</a>
                @endif
            </div>

            <div class="mt-3 grid grid-cols-7 text-center">
                @foreach ($week as $ds => $d)
                    @php
                        $isToday = $d['date']->isSameDay(today());
                        $isOff   = $d['off'] || $d['holiday'];
                    @endphp
                    <div wire:key="week-{{ $ds }}" class="flex flex-col items-center gap-1.5 py-1">
                        <span class="text-[11px] {{ $isOff ? 'text-slate-400' : 'text-slate-600' }}">{{ $d['date']->translatedFormat('D') }}</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold
                                     {{ $isToday ? 'bg-brand-700 text-white' : ($isOff ? 'text-slate-400' : 'text-slate-900') }}">{{ $d['date']->day }}</span>
                        @if ($d['holiday'])
                            <span class="h-1.5 w-1.5 rotate-45 bg-sky-500"></span>
                        @elseif ($d['off'])
                            <span class="h-1.5 w-1.5 rounded-full border border-slate-400"></span>
                        @else
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="mt-2 text-sm text-slate-700">
                {{ $offNames ? 'Libur ' . \Illuminate\Support\Arr::join($offNames, ', ', ' dan ') . '.' : 'Tidak ada hari libur jadwal minggu ini.' }}
                @if ($holNames) Libur nasional: {{ implode(', ', $holNames) }}. @endif
            </p>

            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>Masuk</span>
                <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full border border-slate-400"></span>Libur</span>
                <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rotate-45 bg-sky-500"></span>Libur nasional</span>
            </div>

            @unless ($weekConfigured)
                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
                    Jadwal minggu ini belum diatur Admin, sementara memakai pola standar.
                </p>
            @endunless
        </section>
    @endif

    {{-- ================= BULAN INI ================= --}}
    @if ($sum)
        <section class="mt-7 border-t border-slate-200 pt-6">
            <div class="flex items-baseline justify-between">
                <h2 class="text-[15px] font-bold text-slate-900">Bulan {{ now()->translatedFormat('F') }}</h2>
                @if ($has('app.history'))
                    <a wire:navigate href="{{ route('app.history') }}" class="text-xs font-semibold text-brand-700 hover:underline {{ $focus }}">Lihat riwayat</a>
                @endif
            </div>

            <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-slate-200" role="img"
                 aria-label="Proporsi kehadiran bulan ini">
                @foreach ($seg as [$label, $n, $color])
                    @if ($n > 0)
                        <div class="{{ $color }}" style="width: {{ round($n / $total * 100, 2) }}%"></div>
                    @endif
                @endforeach
            </div>

            <div class="mt-3 grid grid-cols-2 gap-x-8">
                @foreach ($seg as [$label, $n, $color])
                    <div class="flex items-center justify-between py-1.5 text-sm">
                        <span class="flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 rounded-sm {{ $color }}"></span>{{ $label }}</span>
                        <span class="font-semibold tabular-nums text-slate-900">{{ $n }}</span>
                    </div>
                @endforeach
            </div>

            @if (! is_null($sum['pct']))
                <p class="mt-1 text-xs text-slate-500">Kehadiran {{ $sum['pct'] }}% dari {{ $sum['hari_kerja'] }} hari kerja sampai hari ini.</p>
            @else
                <p class="mt-1 text-xs text-slate-500">Belum ada hari kerja yang berjalan bulan ini.</p>
            @endif
        </section>
    @endif

    {{-- ================= MENU ================= --}}
    <nav class="mt-7 divide-y divide-slate-200 border-y border-slate-200" aria-label="Menu">
        @foreach ($menu as [$title, $sub, $route, $spa, $ic])
            <a href="{{ route($route) }}" @if ($spa) wire:navigate @endif
               class="flex items-center gap-3.5 py-3.5 {{ $focus }}">
                <span class="text-slate-500">{!! $svg($ic) !!}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[15px] font-semibold text-slate-900">{{ $title }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ $sub }}</span>
                </span>
                <span class="text-slate-400">{!! $svg('chev', 'h-4 w-4') !!}</span>
            </a>
        @endforeach
    </nav>

    {{-- ================= KELUAR ================= --}}
    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="text-sm font-semibold text-red-700 underline-offset-2 hover:underline {{ $focus }}">Keluar dari akun</button>
    </form>
</div>