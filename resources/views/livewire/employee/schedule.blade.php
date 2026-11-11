<div class="pb-6">
    <div class="rounded-b-3xl bg-gradient-to-br from-indigo-600 to-violet-600 px-5 pb-14 pt-6 text-white">
        <a wire:navigate href="{{ route('app.home') }}" class="text-sm text-white/80">&larr; Kembali</a>
        <h1 class="mt-2 text-xl font-bold">Jadwal Saya</h1>
        @if ($employee->shift)
            <p class="text-xs text-white/75">
                Shift {{ $employee->shift->name }}
                &middot; {{ substr($employee->shift->start_time, 0, 5) }}&ndash;{{ substr($employee->shift->end_time, 0, 5) }}
            </p>
        @endif
    </div>

    <div class="-mt-8 space-y-3 px-5">
        {{-- Navigasi minggu --}}
        <div class="flex items-center justify-between rounded-2xl bg-white p-2 shadow-md">
            <button type="button" wire:click="prevWeek" class="h-10 w-10 rounded-xl text-lg text-slate-600 active:bg-slate-100">&lsaquo;</button>
            <div class="text-center">
                <p class="text-sm font-semibold">{{ $mon->translatedFormat('d M') }} &ndash; {{ $mon->copy()->addDays(6)->translatedFormat('d M Y') }}</p>
                <p class="text-[11px] text-slate-500">{{ $workCount }} hari masuk &middot; {{ 7 - $workCount }} hari libur</p>
            </div>
            <button type="button" wire:click="nextWeek" class="h-10 w-10 rounded-xl text-lg text-slate-600 active:bg-slate-100">&rsaquo;</button>
        </div>

        @unless ($isThisWeek)
            <button type="button" wire:click="thisWeek" class="w-full text-center text-xs font-semibold text-indigo-600">Kembali ke minggu ini</button>
        @endunless

        @unless ($configured)
            <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                Jadwal minggu ini belum diatur HRD, sementara memakai pola standar (Sabtu dan Minggu libur).
            </p>
        @endunless

        {{-- Daftar hari --}}
        <div class="space-y-2">
            @foreach ($days as $ds => $d)
                @php
                    $isToday = $d['date']->isSameDay(today());
                    $tone = $d['holiday'] ? 'bg-blue-100 text-blue-700' : ($d['off'] ? 'bg-slate-100 text-slate-500' : 'bg-green-100 text-green-700');
                    $pill = $d['holiday'] ? 'bg-blue-500 text-white' : ($d['off'] ? 'bg-slate-300 text-slate-700' : 'bg-green-500 text-white');
                @endphp
                <div wire:key="day-{{ $ds }}"
                     class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm {{ $isToday ? 'ring-2 ring-indigo-400' : '' }}">
                    <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl {{ $tone }}">
                        <span class="text-base font-bold leading-none">{{ $d['date']->day }}</span>
                        <span class="mt-0.5 text-[9px] uppercase leading-none">{{ $d['date']->translatedFormat('D') }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">
                            {{ $d['date']->translatedFormat('l') }}
                            @if ($isToday) <span class="ml-1 text-[10px] font-semibold text-indigo-600">Hari ini</span> @endif
                        </p>
                        <p class="truncate text-xs text-slate-500">
                            @if ($d['holiday'])
                                {{ $d['holiday'] }}
                            @elseif (! $d['off'] && $employee->shift)
                                {{ substr($employee->shift->start_time, 0, 5) }}&ndash;{{ substr($employee->shift->end_time, 0, 5) }}
                            @else
                                Tidak ada jadwal kerja
                            @endif
                        </p>
                    </div>

                    <span class="shrink-0 rounded-lg px-3 py-1 text-xs font-bold {{ $pill }}">
                        {{ $d['holiday'] ? 'Libur nasional' : ($d['off'] ? 'Libur' : 'Masuk') }}
                    </span>
                </div>
            @endforeach
        </div>

        <p class="px-1 text-center text-[11px] text-slate-400">
            Jadwal diatur oleh HRD. Hubungi HRD bila ada yang perlu ditukar.
        </p>
    </div>
</div>