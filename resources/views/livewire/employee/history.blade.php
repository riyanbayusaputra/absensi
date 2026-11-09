@php
    $labels = \App\Services\AttendanceCalendar::LABELS;

    // label berwarna pekat (daftar dan chip kalender)
    $pill = [
        'H' => 'bg-green-500 text-white',
        'T' => 'bg-amber-400 text-amber-950',
        'A' => 'bg-red-500 text-white',
        'C' => 'bg-blue-500 text-white',
        'S' => 'bg-purple-500 text-white',
        'I' => 'bg-teal-500 text-white',
        'K' => 'bg-indigo-500 text-white',
        'L' => 'bg-slate-200 text-slate-500',
        'B' => 'bg-yellow-200 text-yellow-800',
        ''  => 'bg-slate-100 text-slate-400',
    ];
    $short = [
        'H' => 'Hadir', 'T' => 'Telat', 'A' => 'Alfa', 'C' => 'Cuti', 'S' => 'Sakit',
        'I' => 'Izin', 'K' => 'Cuti', 'L' => '', 'B' => 'Belum', '' => '',
    ];
    $chips = [
        'semua' => 'Semua', 'hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'alfa' => 'Alfa', 'cuti' => 'Cuti/Izin',
    ];

    $sum   = $cal['summary'];
    $today = today();
    $dur   = fn (int $m) => intdiv($m, 60) . 'j ' . ($m % 60) . 'm';
@endphp

<div class="pb-6">

    {{-- ================= HEADER ================= --}}
    <div class="rounded-b-3xl bg-indigo-600 px-5 pb-16 pt-5 text-white">
        <a wire:navigate href="{{ route('app.home') }}" class="text-sm text-white/80">&larr; Kembali</a>
        <h1 class="mt-2 text-xl font-bold">Riwayat Absensi</h1>

        <div class="mt-4 grid grid-cols-5 gap-2">
            <select wire:model.live="mon"
                    class="col-span-3 rounded-xl border-0 bg-white/95 py-2.5 text-sm font-semibold text-slate-700">
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" @disabled($year === now()->year && $m > now()->month)>
                        {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                    </option>
                @endforeach
            </select>
            <select wire:model.live="year"
                    class="col-span-2 rounded-xl border-0 bg-white/95 py-2.5 text-sm font-semibold text-slate-700">
                @foreach ($years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ================= 3 KARTU UTAMA ================= --}}
    <div class="-mt-10 grid grid-cols-3 gap-3 px-5">
        <div class="rounded-2xl bg-white p-3 text-center shadow-md">
            <p class="text-2xl font-bold text-green-600">{{ $sum['hadir'] }}</p>
            <p class="text-xs text-slate-500">Hadir</p>
        </div>
        <div class="rounded-2xl bg-white p-3 text-center shadow-md">
            <p class="text-2xl font-bold text-amber-500">{{ $sum['terlambat'] }}</p>
            <p class="text-xs text-slate-500">Terlambat</p>
        </div>
        <div class="rounded-2xl bg-white p-3 text-center shadow-md">
            <p class="text-2xl font-bold text-red-500">{{ $sum['alfa'] }}</p>
            <p class="text-xs text-slate-500">Alfa</p>
        </div>
    </div>

    <div class="space-y-3 px-5 pt-3">

        {{-- ================= HARI INI & KEMARIN ================= --}}
        <div class="grid grid-cols-2 gap-3">
            @foreach ($quick as $title => $q)
                @php($qa = $q['att'])
                <button type="button" wire:click="openDay('{{ $q['date']->toDateString() }}')" wire:key="quick-{{ $title }}"
                        class="rounded-2xl bg-white p-3 text-left shadow-sm active:bg-slate-50">
                    <div class="flex items-center justify-between gap-1">
                        <p class="text-[11px] font-semibold uppercase text-slate-400">{{ $title }}</p>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $pill[$q['code']] }}">
                            {{ $q['code'] === '' ? '-' : $labels[$q['code']] }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $q['date']->translatedFormat('D, d M') }}</p>
                    <p class="mt-0.5 text-sm font-semibold">
                        {{ $qa?->check_in_at?->format('H:i') ?? '--:--' }} &ndash; {{ $qa?->check_out_at?->format('H:i') ?? '--:--' }}
                    </p>
                </button>
            @endforeach
        </div>

        {{-- ================= RINGKASAN BULAN ================= --}}
        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-400">Ringkasan bulan ini</p>
            <div class="mt-3 grid grid-cols-3 gap-x-3 gap-y-3 text-center">
                <div><p class="text-base font-bold text-slate-800">{{ $sum['hari_kerja'] }}</p><p class="text-[11px] text-slate-500">Hari kerja</p></div>
                <div><p class="text-base font-bold text-blue-600">{{ $sum['leave'] }}</p><p class="text-[11px] text-slate-500">Cuti/Izin</p></div>
                <div><p class="text-base font-bold text-slate-800">{{ is_null($sum['pct']) ? '-' : $sum['pct'] . '%' }}</p><p class="text-[11px] text-slate-500">Kehadiran</p></div>
                <div><p class="text-base font-bold text-slate-800">{{ $sum['avg_in'] ?? '-' }}</p><p class="text-[11px] text-slate-500">Rata-rata masuk</p></div>
                <div><p class="text-base font-bold text-slate-800">{{ $dur($sum['work_min']) }}</p><p class="text-[11px] text-slate-500">Jam kerja</p></div>
                <div><p class="text-base font-bold text-slate-800">{{ $sum['late_min'] }} mnt</p><p class="text-[11px] text-slate-500">Total telat</p></div>
            </div>
        </div>

        {{-- ================= JUDUL + TOGGLE ================= --}}
        <div class="flex items-end justify-between pt-1">
            <div>
                <h2 class="text-base font-bold">Riwayat Absensi</h2>
                <p class="text-xs text-slate-500">{{ $counts['semua'] }} catatan</p>
            </div>
            <div class="flex rounded-xl bg-slate-200 p-1 text-xs font-semibold">
                <button type="button" wire:click="setMode('daftar')"
                        class="rounded-lg px-3 py-1.5 {{ $mode === 'daftar' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500' }}">Daftar</button>
                <button type="button" wire:click="setMode('kalender')"
                        class="rounded-lg px-3 py-1.5 {{ $mode === 'kalender' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500' }}">Kalender</button>
            </div>
        </div>

        {{-- ======================= DAFTAR ======================= --}}
        @if ($mode === 'daftar')
            <div class="-mx-5 flex gap-2 overflow-x-auto px-5 pb-1">
                @foreach ($chips as $key => $text)
                    <button type="button" wire:click="setFilter('{{ $key }}')" wire:key="chip-{{ $key }}"
                            class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold
                                   {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 shadow-sm' }}">
                        {{ $text }} ({{ $counts[$key] }})
                    </button>
                @endforeach
            </div>

            <div class="space-y-2">
                @forelse ($list as $d)
                    @php($ds = $d['date']->toDateString())
                    @php($a = $d['att'])
                    @php($open = $selected === $ds)
                    <div wire:key="row-{{ $ds }}"
                         class="overflow-hidden rounded-2xl bg-white shadow-sm {{ $open ? 'ring-2 ring-indigo-300' : '' }}">
                        <button type="button" wire:click="selectDay('{{ $ds }}')"
                                class="flex w-full items-center justify-between gap-3 p-4 text-left">
                            <div class="min-w-0">
                                <p class="font-semibold">
                                    {{ $d['date']->translatedFormat('d M Y') }}
                                    <span class="text-xs font-normal text-slate-400">{{ $d['date']->translatedFormat('D') }}</span>
                                </p>
                                <p class="mt-0.5 truncate text-sm text-slate-500">
                                    @if ($a && ! $d['rejected'])
                                        {{ $a->check_in_at?->format('H:i') ?? '--:--' }} &ndash; {{ $a->check_out_at?->format('H:i') ?? '--:--' }}
                                        @if ($a->work_minutes) &middot; {{ $dur($a->work_minutes) }} @endif
                                        @if ($a->late_minutes) &middot; telat {{ $a->late_minutes }} mnt @endif
                                    @elseif ($d['code'] === 'A')
                                        --:-- &ndash; --:--
                                    @elseif ($d['code'] === 'B')
                                        Belum absen hari ini
                                    @elseif ($d['leave'])
                                        {{ $d['leave']['name'] }}{{ $d['leave']['half'] ? ' (1/2 hari)' : '' }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-lg px-3 py-1 text-xs font-bold {{ $pill[$d['code']] }}">
                                    {{ $d['leave'] && ! in_array($d['code'], ['H', 'T', 'A', 'B'], true) ? $d['leave']['name'] : $labels[$d['code']] }}
                                </span>
                                <span class="text-slate-300">{!! $open ? '&#9652;' : '&#9662;' !!}</span>
                            </div>
                        </button>

                        @if ($open)
                            <div class="border-t border-slate-100 px-4 pb-4 pt-3">
                                @include('partials.day-detail', ['d' => $d, 'today' => $today, 'backdate' => $backdate])
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="rounded-2xl bg-white py-10 text-center text-sm text-slate-400 shadow-sm">
                        Tidak ada catatan untuk filter ini.
                    </p>
                @endforelse
            </div>

        {{-- ======================= KALENDER ======================= --}}
        @else
            <div class="rounded-2xl bg-white p-3 shadow-sm">
                <p class="mb-2 text-center text-sm font-bold">{{ $cal['month']->translatedFormat('F Y') }}</p>

                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-semibold text-slate-400">
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h)
                        <div class="py-1">{{ $h }}</div>
                    @endforeach
                </div>

                <div class="mt-1 space-y-1">
                    @foreach ($cal['weeks'] as $week)
                        <div class="grid grid-cols-7 gap-1">
                            @foreach ($week as $ds)
                                @if ($ds === null)
                                    <div></div>
                                @else
                                    @php($c = $cal['days'][$ds])
                                    <button type="button" wire:click="selectDay('{{ $ds }}')" wire:key="day-{{ $ds }}"
                                            @disabled($c['code'] === '')
                                            class="flex h-14 flex-col items-center justify-between rounded-xl py-1
                                                   {{ $c['code'] === 'L' ? 'bg-slate-50' : '' }}
                                                   {{ $selected === $ds ? 'bg-indigo-50 ring-2 ring-indigo-500' : '' }}
                                                   {{ $c['date']->isSameDay($today) ? 'border-2 border-indigo-400' : '' }}">
                                        <span class="text-[11px] font-semibold {{ in_array($c['code'], ['L', ''], true) ? 'text-slate-400' : 'text-slate-700' }}">
                                            {{ $c['date']->day }}
                                        </span>
                                        @if ($short[$c['code']] !== '')
                                            <span class="rounded px-1 text-[8px] font-bold leading-4 {{ $pill[$c['code']] }}">{{ $short[$c['code']] }}</span>
                                        @else
                                            <span class="h-4"></span>
                                        @endif
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-slate-600">
                    <span><span class="inline-block h-2.5 w-2.5 rounded bg-green-500 align-middle"></span> Hadir</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded bg-amber-400 align-middle"></span> Telat</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded bg-red-500 align-middle"></span> Alfa</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded bg-blue-500 align-middle"></span> Cuti</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded bg-purple-500 align-middle"></span> Sakit</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded bg-teal-500 align-middle"></span> Izin</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded bg-slate-200 align-middle"></span> Libur</span>
                </div>
            </div>

            @if ($selected)
                @php($d = $cal['days'][$selected])
                <div class="rounded-2xl bg-white p-4 shadow-sm" wire:key="detail-{{ $selected }}">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <p class="font-semibold">{{ $d['date']->translatedFormat('l, d F Y') }}</p>
                        <span class="shrink-0 rounded-lg px-3 py-1 text-xs font-bold {{ $pill[$d['code']] }}">{{ $labels[$d['code']] }}</span>
                    </div>
                    @include('partials.day-detail', ['d' => $d, 'today' => $today, 'backdate' => $backdate])
                </div>
            @else
                <p class="rounded-2xl bg-white p-4 text-center text-xs text-slate-400 shadow-sm">Ketuk tanggal untuk melihat detail.</p>
            @endif
        @endif

        @if (Route::has('app.recap.pdf'))
            <a href="{{ route('app.recap.pdf', $ym) }}"
               class="block rounded-xl border border-indigo-200 bg-white py-2.5 text-center text-sm font-semibold text-indigo-600">
                Unduh rekap bulan ini (PDF)
            </a>
        @endif
    </div>
</div>