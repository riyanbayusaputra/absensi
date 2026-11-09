<div class="p-5" x-data="faceEnroll(@js($cfg))">
    @push('scripts')
        <script src="{{ config('attendance.face.script_url') }}"></script>
        <script src="{{ asset('js/face-enroll.js') }}"></script>
    @endpush

    <a wire:navigate href="{{ route('app.home') }}" class="text-sm text-slate-500">&larr; Kembali</a>
    <h1 class="mt-2 text-xl font-bold">Registrasi Wajah</h1>

    @if (session('status'))
        <div class="mt-3 rounded-xl bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    {{-- Status --}}
    @if ($approved)
        <div class="mt-4 rounded-2xl bg-green-50 p-4 text-sm text-green-800">
            <p class="font-semibold">Wajah sudah terdaftar</p>
            <p>Disetujui {{ ($approved->reviewed_at ?? $approved->created_at)->translatedFormat('d M Y') }}</p>
        </div>
    @endif

    @if ($latest?->status === 'pending')
        <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">
            <p class="font-semibold">Menunggu persetujuan HRD</p>
            <p>Dikirim {{ $latest->created_at->diffForHumans() }}. Anda belum bisa mengirim ulang.</p>
        </div>
    @elseif ($latest?->status === 'rejected')
        <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Registrasi ditolak</p>
            <p>{{ $latest->reject_reason }}</p>
        </div>
    @elseif (! $approved)
        <div class="mt-4 rounded-2xl bg-slate-100 p-4 text-sm text-slate-600">
            Anda belum mendaftarkan wajah. Pendaftaran diperlukan untuk absen.
        </div>
    @endif

    {{-- Form pendaftaran --}}
    @if ($latest?->status !== 'pending')
        <div class="mt-5">
            <h2 class="mb-2 font-semibold">{{ $approved ? 'Perbarui foto wajah' : 'Daftarkan foto wajah' }}</h2>

            <ul class="mb-3 list-disc space-y-0.5 pl-5 text-xs text-slate-500">
                <li>Selfie menghadap lurus, cahaya terang</li>
                <li>Tanpa masker, kacamata hitam, atau topi</li>
                <li>Hanya satu wajah di foto</li>
            </ul>

            <label class="mb-3 flex items-start gap-2 text-xs text-slate-600">
                <input type="checkbox" x-model="consent" class="mt-0.5 rounded">
                <span>Saya menyetujui data wajah saya diproses untuk keperluan absensi. Data hanya diakses HRD
                    berwenang dan dihapus sesuai kebijakan retensi perusahaan.</span>
            </label>

            {{-- Viewport: kamera / preview --}}
            <div wire:ignore x-show="mode === 'camera' || mode === 'preview' || mode === 'saving'"
                 class="relative mx-auto aspect-[3/4] w-full overflow-hidden rounded-3xl bg-black">
                <video x-ref="video" x-show="mode === 'camera'"
                       class="h-full w-full -scale-x-100 object-cover" autoplay playsinline muted></video>

                <img x-show="mode === 'preview' || mode === 'saving'" :src="photo"
                     class="h-full w-full object-cover" alt="Preview foto">

                <div x-show="mode === 'camera'" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="h-[70%] w-[62%] rounded-[50%] border-4 transition-colors"
                         :class="faceOk ? 'border-green-400' : 'border-white/70'"></div>
                </div>

                <div x-show="mode === 'camera'"
                     class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-4 text-center text-sm text-white">
                    <span x-text="message"></span>
                </div>
            </div>

            {{-- Pesan di luar viewport --}}
            <p class="mt-3 text-center text-sm text-slate-600"
               x-show="mode === 'idle' && message" x-text="message"></p>

            @error('face') <p class="mt-3 text-center text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- Tombol: idle --}}
            <div class="mt-4 space-y-2" x-show="mode === 'idle'">
                <button type="button" @click="startCamera()"
                        class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white active:bg-indigo-700">
                    Ambil Foto dengan Kamera
                </button>

                @if ($cfg['gallery'])
                    <input type="file" accept="image/*" class="hidden" x-ref="file" @change="pickFile($event)">
                    <button type="button" @click="$refs.file.click()"
                            class="w-full rounded-xl border border-slate-300 bg-white py-3 font-semibold text-slate-700 active:bg-slate-100">
                        Pilih dari Galeri
                    </button>
                @endif
            </div>

            {{-- Tombol: kamera --}}
            <div class="mt-4 space-y-2" x-show="mode === 'camera'">
                <button type="button" @click="shoot()" :disabled="!faceOk"
                        class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white disabled:opacity-40">
                    Ambil Foto
                </button>
                <button type="button" @click="stopCamera(); mode = 'idle'; message = ''"
                        class="w-full py-2 text-sm text-slate-500">Batal</button>
            </div>

            {{-- Tombol: preview --}}
            <div class="mt-4 space-y-2" x-show="mode === 'preview' || mode === 'saving'">
                <button type="button" @click="submit()" :disabled="mode === 'saving'"
                        class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white disabled:opacity-50">
                    <span x-show="mode === 'preview'">Kirim untuk Persetujuan</span>
                    <span x-show="mode === 'saving'">Mengirim...</span>
                </button>
                <button type="button" @click="retake()" :disabled="mode === 'saving'"
                        class="w-full py-2 text-sm text-slate-500">Ulangi foto</button>
            </div>
        </div>
    @endif
</div>