@php
    $r = fn (string $name) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';
    $type = $next['type'] ?? null;
    $att  = $next['attendance'] ?? null;
    $dur  = fn (int $m) => intdiv($m, 60) . 'j ' . ($m % 60) . 'm';
    $sinceMs = ($type === 'out' && $att?->check_in_at) ? $att->check_in_at->getTimestamp() * 1000 : 'null';
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

    $icon = [
        'pin'      => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
        'clock'    => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
        'face'     => 'M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75z',
        'bell'     => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
        'logout'   => 'M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75',
    ];
    $svg = fn (string $key, string $cls = 'h-5 w-5') =>
        '<svg class="' . $cls . '" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="' . $icon[$key] . '"/></svg>';
@endphp

<div x-data="{
        off: {{ $serverMs }} - Date.now(),
        tz: '{{ $tz }}',
        now: new Date(),
        since: {{ $sinceMs }},
        showNotif: {{ $notifs->isNotEmpty() ? 'true' : 'false' }},
        t: null,
        init() { this.tick(); this.t = setInterval(() => this.tick(), 1000); },
        tick() { this.now = new Date(Date.now() + this.off); },
        destroy() { clearInterval(this.t); },
        time() { return new Intl.DateTimeFormat('id-ID', { timeZone: this.tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(this.now).replace(/\./g, ':'); },
        date() { return new Intl.DateTimeFormat('id-ID', { timeZone: this.tz, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(this.now); },
        hour() { return parseInt(new Intl.DateTimeFormat('en-GB', { timeZone: this.tz, hour: 'numeric', hourCycle: 'h23' }).format(this.now)); },
        greet() { const h = this.hour(); return h < 11 ? 'Selamat pagi' : (h < 15 ? 'Selamat siang' : (h < 18 ? 'Selamat sore' : 'Selamat malam')); },
        worked() { if (!this.since) return ''; const m = Math.max(0, Math.floor((this.now.getTime() - this.since) / 60000)); return Math.floor(m / 60) + 'j ' + (m % 60) + 'm'; }
     }">

    {{-- ================= HEADER + JAM ================= --}}
    <div class="relative overflow-hidden rounded-b-[2rem] bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 px-5 pb-24 pt-7 text-white">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-12 -left-8 h-32 w-32 rounded-full bg-white/10"></div>

        <div class="relative flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if ($employee?->photo)
                    <img src="{{ asset('storage/' . $employee->photo) }}" alt="" class="h-11 w-11 rounded-full border-2 border-white/40 object-cover">
                @else
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 text-sm font-bold">{{ $initials }}</div>
                @endif
                <div>
                    <p class="text-xs text-white/70" x-text="greet()">Halo</p>
                    <p class="font-semibold leading-tight">{{ $user->name }}</p>
                    <p class="text-[11px] text-white/70">
                        {{ collect([$employee?->position?->name, $employee?->department?->name])->filter()->implode(' · ') ?: 'Karyawan' }}
                    </p>
                </div>
            </div>

            <button type="button" @click="showNotif = !showNotif"
                    class="relative flex h-10 w-10 items-center justify-center rounded-full bg-white/15 active:bg-white/25">
                {!! $svg('bell') !!}
                @if ($unread > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold">{{ $unread }}</span>
                @endif
            </button>
        </div>

        <div class="relative mt-7 text-center">
            <p class="text-5xl font-bold tabular-nums tracking-tight" x-text="time()">--:--:--</p>
            <p class="mt-1 text-sm text-white/80" x-text="date()">{{ now()->translatedFormat('l, d F Y') }}</p>
            <p class="mt-1 text-[10px] text-white/50">Waktu server &middot; {{ $tzLabel }}</p>
        </div>
    </div>

    <div class="-mt-16 space-y-4 px-5">

        {{-- ================= NOTIFIKASI ================= --}}
        @if ($notifs->isNotEmpty())
            <div x-show="showNotif" x-cloak x-transition class="rounded-2xl bg-white p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold">Notifikasi</p>
                    <button wire:click="markRead" class="text-xs font-semibold text-indigo-600">Tandai dibaca</button>
                </div>
                <div class="mt-2 divide-y divide-slate-100">
                    @foreach ($notifs as $n)
                        <div class="py-2">
                            <p class="text-sm font-medium">{{ $n->data['title'] ?? '' }}</p>
                            <p class="text-xs text-slate-500">{{ $n->data['body'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ================= KARTU ABSENSI HARI INI ================= --}}
        <div class="rounded-3xl bg-white p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Absensi hari ini</p>

                @if ($type === 'in')
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-700">Belum absen</span>
                @elseif ($type === 'out')
                    <span class="flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-[11px] font-semibold text-green-700">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-green-500"></span> Sedang bekerja
                    </span>
                @elseif ($type === 'done')
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $att?->status === 'terlambat' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700' }}">
                        {{ $att?->status === 'terlambat' ? 'Terlambat' : 'Tepat waktu' }}
                    </span>
                @else
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">Tidak tersedia</span>
                @endif
            </div>

            @if ($employee?->shift)
                <p class="mt-1 text-xs text-slate-500">
                    Shift {{ $employee->shift->name }}
                    &middot; {{ substr($employee->shift->start_time, 0, 5) }}&ndash;{{ substr($employee->shift->end_time, 0, 5) }}
                </p>
            @endif

            @if (in_array($type, ['out', 'done'], true))
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-slate-50 py-3">
                        <p class="text-[11px] text-slate-400">Masuk</p>
                        <p class="text-lg font-bold">{{ $att->check_in_at->format('H:i') }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 py-3">
                        <p class="text-[11px] text-slate-400">Pulang</p>
                        <p class="text-lg font-bold">{{ $att->check_out_at?->format('H:i') ?? '--:--' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 py-3">
                        <p class="text-[11px] text-slate-400">Durasi</p>
                        @if ($type === 'out')
                            <p class="text-lg font-bold text-indigo-600 tabular-nums" x-text="worked()">0j 0m</p>
                        @else
                            <p class="text-lg font-bold">{{ $att->work_minutes ? $dur($att->work_minutes) : '-' }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if ($type === 'in')
                <a href="{{ $r('app.attend') }}"
                   class="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 py-3.5 font-semibold text-white shadow-lg shadow-indigo-600/30 active:scale-[.99]">
                    {!! $svg('pin') !!} Absen Masuk
                </a>
            @elseif ($type === 'out')
                <a href="{{ $r('app.attend') }}"
                   class="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-rose-500 py-3.5 font-semibold text-white shadow-lg shadow-rose-500/30 active:scale-[.99]">
                    {!! $svg('pin') !!} Absen Pulang
                </a>
            @elseif ($type === 'done')
                <p class="mt-4 rounded-2xl bg-green-50 py-3 text-center text-sm font-medium text-green-700">
                    Absen hari ini sudah lengkap. Terima kasih!
                </p>
            @else
                <div class="mt-4 rounded-2xl bg-amber-50 p-3 text-sm text-amber-800">
                    {{ $next['reason'] ?? 'Akun belum terhubung dengan data karyawan.' }}
                    @if (! empty($next['cta']))
                        <a href="{{ $next['cta'] }}" class="mt-2 block rounded-xl bg-amber-500 py-2 text-center font-semibold text-white">Daftarkan wajah</a>
                    @endif
                </div>
            @endif
        </div>

        {{-- ================= RINGKASAN BULAN INI ================= --}}
        @if ($summary)
            <a wire:navigate href="{{ $r('app.history') }}" class="block rounded-3xl bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold">Bulan {{ now()->translatedFormat('F') }}</p>
                    <span class="text-xs font-semibold text-indigo-600">Lihat riwayat &rsaquo;</span>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-green-50 py-3">
                        <p class="text-xl font-bold text-green-600">{{ $summary['hadir'] }}</p>
                        <p class="text-[11px] text-slate-500">Hadir</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 py-3">
                        <p class="text-xl font-bold text-amber-500">{{ $summary['terlambat'] }}</p>
                        <p class="text-[11px] text-slate-500">Terlambat</p>
                    </div>
                    <div class="rounded-2xl bg-red-50 py-3">
                        <p class="text-xl font-bold text-red-500">{{ $summary['alfa'] }}</p>
                        <p class="text-[11px] text-slate-500">Alfa</p>
                    </div>
                </div>
            </a>
        @endif

        {{-- ================= MENU CEPAT ================= --}}
        <div class="grid grid-cols-2 gap-3">
            <a wire:navigate href="{{ $r('app.history') }}" class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm active:bg-slate-50">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">{!! $svg('clock') !!}</span>
                <span>
                    <span class="block text-sm font-semibold">Riwayat</span>
                    <span class="block text-[11px] text-slate-500">Daftar &amp; kalender</span>
                </span>
            </a>

            <a wire:navigate href="{{ $r('app.leave') }}" class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm active:bg-slate-50">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">{!! $svg('calendar') !!}</span>
                <span>
                    <span class="block text-sm font-semibold">Cuti &amp; Izin</span>
                    <span class="block text-[11px] text-slate-500">
                        {{ $annual ? 'Sisa ' . ($annual->remaining + 0) . ' hari' : 'Ajukan cuti' }}
                    </span>
                </span>
            </a>

            {{-- halaman kamera: tautan biasa, tanpa wire:navigate --}}
            <a href="{{ $r('app.face') }}" class="col-span-2 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm active:bg-slate-50">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600">{!! $svg('face') !!}</span>
                <span class="flex-1">
                    <span class="block text-sm font-semibold">Registrasi Wajah</span>
                    <span class="block text-[11px] text-slate-500">{{ $employee?->face_status ?? 'Akun belum terhubung data karyawan' }}</span>
                </span>
                <span class="text-slate-300">&rsaquo;</span>
            </a>
        </div>

        {{-- ================= KELUAR ================= --}}
        <form method="POST" action="{{ route('logout') }}" class="pt-1">
            @csrf
            <button class="flex w-full items-center justify-center gap-2 rounded-2xl border border-red-100 bg-white py-3 text-sm font-semibold text-red-600 active:bg-red-50">
                {!! $svg('logout', 'h-4 w-4') !!} Keluar
            </button>
        </form>
    </div>
</div>