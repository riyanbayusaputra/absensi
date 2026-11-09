<div class="p-5">
    <a wire:navigate href="{{ route('app.home') }}" class="text-sm text-slate-500">&larr; Kembali</a>

    @if ($next['type'] === 'blocked')
        <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">
            <p class="font-semibold">Belum bisa absen</p>
            <p class="mt-1">{{ $next['reason'] }}</p>
            @if (! empty($next['cta']))
                <a href="{{ $next['cta'] }}" class="mt-3 inline-block rounded-lg bg-amber-600 px-3 py-2 font-semibold text-white">Daftarkan wajah</a>
            @endif
        </div>

    @elseif ($next['type'] === 'done')
        @php($a = $next['attendance'])
        <div class="mt-4 rounded-2xl bg-green-50 p-5 text-sm text-green-800">
            <p class="text-lg font-bold">Absen hari ini lengkap</p>
            <p class="mt-2">Masuk: <b>{{ $a->check_in_at->format('H:i') }}</b>
                ({{ $a->status === 'terlambat' ? "terlambat {$a->late_minutes} mnt" : 'tepat waktu' }})</p>
            <p>Pulang: <b>{{ $a->check_out_at->format('H:i') }}</b>
                @if ($a->early_leave_minutes) (pulang cepat {{ $a->early_leave_minutes }} mnt) @endif</p>
        </div>

    @else
        @push('head')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        @endpush
        @push('scripts')
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script src="{{ config('attendance.face.script_url') }}"></script>
            <script src="{{ asset('js/face-attend.js') }}"></script>
        @endpush

        <div wire:ignore x-data="faceAttend(@js($scanCfg))">
            <h1 class="mt-2 text-xl font-bold">
                {{ $next['type'] === 'in' ? 'Absen Masuk' : 'Absen Pulang' }}
            </h1>
            <p class="text-xs text-slate-500">
                Shift {{ $employee->shift->name }}
                ({{ substr($employee->shift->start_time, 0, 5) }}&ndash;{{ substr($employee->shift->end_time, 0, 5) }})
            </p>

            {{-- ===== Peta Leaflet ===== --}}
            <div class="relative isolate mt-3 overflow-hidden rounded-2xl shadow-sm">
                <div x-ref="map" class="w-full bg-slate-200 transition-all"
                     :class="(mode === 'idle' || mode === 'error' || mode === 'done') ? 'h-56' : 'h-36'"></div>

                <div class="absolute left-2 top-2" style="z-index:1000">
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold text-white shadow"
                          :class="areaState() === 'in' ? 'bg-green-600' : (areaState() === 'out' ? 'bg-red-600' : 'bg-slate-700')"
                          x-text="areaText()"></span>
                </div>

                <button type="button" @click="fitMap()"
                        class="absolute bottom-2 right-2 rounded-lg bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow"
                        style="z-index:1000">Pusatkan</button>
            </div>

            {{-- Status GPS --}}
            <div class="mt-2 flex items-center gap-2 rounded-xl bg-white p-3 text-xs shadow-sm">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="gpsOk() ? 'bg-green-500' : 'bg-amber-400'"></span>
                <span x-text="gpsText()"></span>
            </div>
            <p class="mt-1 px-1 text-[10px] text-slate-400">
                Lingkaran biru muda = margin akurasi GPS Anda. Lingkaran ungu = area absen kantor.
            </p>

            {{-- Viewport kamera --}}
            <div x-show="mode === 'scan' || mode === 'loading' || mode === 'sending'"
                 class="relative mx-auto mt-3 aspect-[3/4] w-full overflow-hidden rounded-3xl bg-black">
                <video x-ref="video" class="h-full w-full -scale-x-100 object-cover" autoplay playsinline muted></video>

                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="h-[70%] w-[62%] rounded-[50%] border-4 transition-colors"
                         :class="ready() ? 'border-green-400' : (faceOk ? 'border-sky-300' : 'border-white/70')"></div>
                </div>

                <div class="absolute inset-x-0 top-0 flex justify-center gap-2 p-3" x-show="mode === 'scan'">
                    <span class="rounded-full px-2 py-0.5 text-[10px] text-white"
                          :class="gpsOk() && areaState() !== 'out' ? 'bg-green-600' : 'bg-white/30'">Lokasi</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] text-white"
                          :class="ready() ? 'bg-green-600' : 'bg-white/30'">Wajah</span>
                </div>

                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-4 text-center text-sm text-white">
                    <span x-text="msg"></span>
                </div>
            </div>

            {{-- Mulai --}}
            <div class="mt-4" x-show="mode === 'idle'">
                <ul class="mb-3 list-disc space-y-0.5 pl-5 text-xs text-slate-500">
                    <li>Aktifkan GPS (mode akurasi tinggi) dan izinkan lokasi serta kamera</li>
                    <li>Wajah menghadap lurus, cahaya cukup, tanpa masker</li>
                    <li>Tahan sebentar, absen terkirim otomatis</li>
                </ul>
                <button type="button" @click="start()"
                        class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white active:bg-indigo-700">
                    Mulai {{ $next['type'] === 'in' ? 'Absen Masuk' : 'Absen Pulang' }}
                </button>
            </div>

            <div class="mt-4 text-center text-sm text-slate-500" x-show="mode === 'scan'">
                <button type="button" @click="stopCamera(); mode = 'idle'; resetFace()" class="py-2">Batal</button>
            </div>

            {{-- Berhasil --}}
            <div x-show="mode === 'done'" class="mt-4 rounded-2xl bg-green-50 p-5 text-center text-green-800">
                <p class="text-3xl">&#10003;</p>
                <p class="mt-1 text-lg font-bold"
                   x-text="result && result.type === 'in' ? 'Absen masuk tercatat' : 'Absen pulang tercatat'"></p>
                <p class="mt-1 text-2xl font-bold" x-text="result && result.time"></p>
                <p class="mt-1 text-sm" x-text="result && result.status"></p>
                <p class="mt-2 text-xs opacity-80"
                   x-text="result && (result.location + ' · ' + result.distance + ' m dari titik')"></p>
            </div>

            {{-- Gagal --}}
            <div x-show="mode === 'error'" class="mt-4 rounded-2xl bg-red-50 p-5 text-sm text-red-800">
                <p class="font-semibold">Absen gagal</p>
                <p class="mt-1" x-text="errorMsg"></p>
                <button type="button" @click="retry()"
                        class="mt-3 w-full rounded-xl bg-red-600 py-2.5 font-semibold text-white">Coba lagi</button>
            </div>
        </div>
    @endif
</div>