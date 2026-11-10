<div class="min-h-screen sm:flex sm:items-center sm:justify-center sm:p-6"
     x-data="{
        show: false,
        off: {{ now()->getTimestamp() * 1000 }} - Date.now(),
        tz: '{{ config('app.timezone') }}',
        now: new Date(),
        t: null,
        init() { this.tick(); this.t = setInterval(() => this.tick(), 1000); },
        tick() { this.now = new Date(Date.now() + this.off); },
        destroy() { clearInterval(this.t); },
        time() { return new Intl.DateTimeFormat('id-ID', { timeZone: this.tz, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(this.now).replace(/\./g, ':'); },
        date() { return new Intl.DateTimeFormat('id-ID', { timeZone: this.tz, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(this.now); }
     }">

    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col bg-white sm:min-h-0 sm:overflow-hidden sm:rounded-[2rem] sm:shadow-2xl sm:shadow-indigo-900/15">

        {{-- ================= HERO ================= --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 px-6 pb-16 pt-10 text-white">
            <div class="pointer-events-none absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-10 h-40 w-40 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute right-10 top-24 h-16 w-16 rounded-full bg-white/10"></div>

            <div class="relative flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 shadow-lg shadow-indigo-900/20 backdrop-blur">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.5 2.9 8.5 7 10 4.1-1.5 7-5.5 7-10V6l-7-3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
                    </svg>
                </div>
                <div>
                    {{-- <p class="text-lg font-bold leading-tight">{{ config('app.name') }}</p> --}}
                    <p class="text-[11px] text-white/70">Absensi</p>
                </div>
            </div>

            <div class="relative mt-10">
                <p class="text-sm text-white/80">Selamat datang kembali</p>
                <p class="mt-1 text-5xl font-bold tabular-nums tracking-tight" x-text="time()">--:--</p>
                <p class="mt-1 text-sm text-white/80" x-text="date()">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>

        {{-- ================= FORM ================= --}}
        <div class="relative -mt-8 flex-1 rounded-t-[2rem] bg-white px-6 pb-8 pt-8">
            <h1 class="text-xl font-bold text-slate-900">Masuk ke akun Anda</h1>
            <p class="mt-1 text-sm text-slate-500">Gunakan email dan password dari administrator.</p>

            <form wire:submit="login" class="mt-6 space-y-4">

                {{-- Email --}}
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        <input id="email" wire:model="email" type="email" placeholder="nama@email.com"
                               autocomplete="username" autocapitalize="none" inputmode="email" autofocus
                               class="w-full rounded-xl border bg-slate-50 py-3.5 pl-12 pr-4 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:bg-white focus:ring-4
                                      @error('email') border-red-300 focus:border-red-400 focus:ring-red-500/10 @else border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/10 @enderror">
                    </div>
                    @error('email')
                        <p class="mt-2 flex items-start gap-1.5 text-sm text-red-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <input id="password" wire:model="password" :type="show ? 'text' : 'password'" placeholder="Masukkan password"
                               autocomplete="current-password"
                               class="w-full rounded-xl border bg-slate-50 py-3.5 pl-12 pr-12 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:bg-white focus:ring-4
                                      @error('password') border-red-300 focus:border-red-400 focus:ring-red-500/10 @else border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/10 @enderror">
                        <button type="button" @click="show = !show" tabindex="-1"
                                class="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 active:bg-slate-100"
                                :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                            <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 flex items-start gap-1.5 text-sm text-red-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                {{-- Ingat saya --}}
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                    <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Ingat saya di perangkat ini
                </label>

                {{-- Tombol --}}
                <button type="submit" wire:loading.attr="disabled" wire:target="login"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3.5 font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/25 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                    <svg wire:loading wire:target="login" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                        <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span wire:loading.remove wire:target="login">Masuk</span>
                    <span wire:loading wire:target="login">Memproses...</span>
                </button>
            </form>

            <div class="mt-6 rounded-xl bg-slate-50 p-3 text-center text-xs text-slate-500">
                Lupa password atau akun belum aktif? <span class="font-semibold text-slate-700">Hubungi Administrator</span>.
            </div>

            <p class="mt-6 text-center text-[11px] text-slate-400">
                &copy; {{ now()->year }} PT.Sakamedia Komunika
            </p>
        </div>
    </div>
</div>