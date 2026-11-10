@php
    $e = $employee;
    $avatar = $e?->photo ? asset('storage/' . $e->photo) : null;
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

    $rows = $e ? [
        'NIK'           => $e->nik,
        'Departemen'    => $e->department?->name ?? '-',
        'Jabatan'       => $e->position?->name ?? '-',
        'Atasan'        => $e->manager?->name ?? '-',
        'Shift'         => $e->shift ? $e->shift->name . ' (' . substr($e->shift->start_time, 0, 5) . '–' . substr($e->shift->end_time, 0, 5) . ')' : '-',
        'Tanggal masuk' => $e->join_date?->translatedFormat('d F Y') ?? '-',
        'Email login'   => $user->email,
    ] : ['Email login' => $user->email];
@endphp

<div x-data="profilePage({{ $forced ? 'true' : 'false' }})" class="pb-8">
    @push('scripts')
        <script>
            window.profilePage = function (forced) {
                return {
                    busy: false, err: '', openPwd: forced, show: false,

                    async resize(file, max = 640) {
                        let src;
                        try {
                            src = await createImageBitmap(file, { imageOrientation: 'from-image' });
                        } catch (_) {
                            src = await new Promise((ok, bad) => {
                                const i = new Image();
                                i.onload = () => ok(i);
                                i.onerror = bad;
                                i.src = URL.createObjectURL(file);
                            });
                        }
                        const w0 = src.width, h0 = src.height;
                        const k = Math.min(1, max / Math.max(w0, h0));
                        const c = document.createElement('canvas');
                        c.width = Math.round(w0 * k);
                        c.height = Math.round(h0 * k);
                        c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
                        if (src.close) src.close();
                        const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.85));
                        return new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
                    },

                    async pick(e) {
                        const f = e.target.files && e.target.files[0];
                        e.target.value = '';
                        if (!f) return;
                        if (!f.type.startsWith('image/')) { this.err = 'File harus berupa gambar.'; return; }

                        this.err = ''; this.busy = true;
                        try {
                            const file = await this.resize(f);
                            await new Promise((ok, bad) => this.$wire.upload('photo', file, ok, bad));
                            await this.$wire.savePhoto();
                        } catch (_) {
                            this.err = 'Gagal mengunggah foto. Coba lagi.';
                        }
                        this.busy = false;
                    },
                };
            };
        </script>
    @endpush

    {{-- ================= HEADER ================= --}}
    <div class="relative overflow-hidden rounded-b-[2rem] bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 px-5 pb-16 pt-6 text-white">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>

        <a href="{{ route('app.home') }}" class="relative text-sm text-white/80">&larr; Kembali</a>

        <div class="relative mt-4 flex flex-col items-center text-center">
            <div class="relative">
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="Foto profil" class="h-24 w-24 rounded-full border-4 border-white/40 object-cover">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white/30 bg-white/20 text-3xl font-bold">{{ $initials }}</div>
                @endif

                @if ($e)
                    <button type="button" @click="$refs.file.click()" :disabled="busy"
                            class="absolute bottom-0 right-0 flex h-8 w-8 items-center justify-center rounded-full bg-white text-indigo-600 shadow-lg disabled:opacity-60">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                    </button>
                    <input type="file" accept="image/*" class="hidden" x-ref="file" @change="pick($event)">
                @endif
            </div>

            <p class="mt-3 text-lg font-bold">{{ $user->name }}</p>
            <p class="text-xs text-white/75">
                {{ collect([$e?->position?->name, $e?->department?->name])->filter()->implode(' · ') ?: 'Akun' }}
            </p>
            <p class="mt-1 text-[11px] text-white/80" x-show="busy" x-cloak>Mengunggah foto...</p>
        </div>
    </div>

    <div class="-mt-8 space-y-4 px-5">

        {{-- Pesan --}}
        @if ($notice)
            <div class="rounded-2xl bg-green-50 p-3 text-sm text-green-700 shadow-sm">{{ $notice }}</div>
        @endif
        <p class="rounded-2xl bg-red-50 p-3 text-sm text-red-700 shadow-sm" x-show="err" x-text="err" x-cloak></p>
        @error('photo') <p class="rounded-2xl bg-red-50 p-3 text-sm text-red-700 shadow-sm">{{ $message }}</p> @enderror

  

        {{-- ================= DATA KEPEGAWAIAN ================= --}}
        <div class="rounded-3xl bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Data kepegawaian</p>
            <div class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($rows as $label => $value)
                    <div class="flex items-start justify-between gap-4 py-2">
                        <span class="shrink-0 text-slate-500">{{ $label }}</span>
                        <span class="text-right font-medium">{{ $value }}</span>
                    </div>
                @endforeach
                @if ($e)
                    <div class="flex items-center justify-between gap-4 py-2">
                        <span class="text-slate-500">Wajah</span>
                        <a href="{{ route('app.face') }}" class="font-medium text-indigo-600">{{ $e->face_status }} &rsaquo;</a>
                    </div>
                @endif
            </div>
            <p class="mt-2 text-[11px] text-slate-400">Data di atas diatur oleh Administrator. Hubungi Administrator bila ada yang salah.</p>
        </div>

        @if ($e)
            {{-- ================= KONTAK ================= --}}
            <div class="rounded-3xl bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kontak</p>
                <form wire:submit="savePhone" class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">Nomor HP</label>
                        <input type="tel" wire:model="phone" inputmode="tel" placeholder="08xxxxxxxxxx"
                               class="w-full rounded-xl border-slate-300 bg-white py-3">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="savePhone"
                            class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white active:bg-indigo-700 disabled:opacity-60">
                        Simpan nomor HP
                    </button>
                </form>

                @if ($e->photo)
                    <button type="button" wire:click="removePhoto" wire:confirm="Hapus foto profil?"
                            class="mt-3 w-full text-center text-xs font-semibold text-red-600">Hapus foto profil</button>
                @endif
            </div>
        @endif

        {{-- ================= KEAMANAN ================= --}}
        <div class="rounded-3xl bg-white p-5 shadow-sm">
            <button type="button" @click="openPwd = !openPwd" class="flex w-full items-center justify-between text-left">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Keamanan</span>
                <span class="text-sm font-semibold text-indigo-600" x-text="openPwd ? 'Tutup' : 'Ganti password'"></span>
            </button>

            <form wire:submit="changePassword" class="mt-3 space-y-3" x-show="openPwd" x-cloak>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Password saat ini</label>
                    <input :type="show ? 'text' : 'password'" wire:model="current_password" autocomplete="current-password"
                           class="w-full rounded-xl border-slate-300 bg-white py-3">
                    @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Password baru</label>
                    <input :type="show ? 'text' : 'password'" wire:model="password" autocomplete="new-password"
                           class="w-full rounded-xl border-slate-300 bg-white py-3">
                    <p class="mt-1 text-[11px] text-slate-400">Minimal 8 karakter, mengandung huruf dan angka.</p>
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">Ulangi password baru</label>
                    <input :type="show ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password"
                           class="w-full rounded-xl border-slate-300 bg-white py-3">
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" x-model="show" class="rounded"> Tampilkan password
                </label>
                <button type="submit" wire:loading.attr="disabled" wire:target="changePassword"
                        class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white active:bg-indigo-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="changePassword">Simpan password baru</span>
                    <span wire:loading wire:target="changePassword">Menyimpan...</span>
                </button>
            </form>
        </div>

        {{-- ================= KELUAR ================= --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="w-full rounded-2xl border border-red-100 bg-white py-3 text-sm font-semibold text-red-600 active:bg-red-50">
                Keluar
            </button>
        </form>
    </div>
</div>