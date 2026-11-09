<div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4 py-6 sm:px-6 sm:py-12">
    <div class="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-indigo-200/50 blur-3xl sm:-left-24 sm:-top-24 sm:h-72 sm:w-72"></div>
    <div class="pointer-events-none absolute -bottom-20 -right-16 h-60 w-60 rounded-full bg-violet-200/50 blur-3xl sm:-bottom-28 sm:-right-20 sm:h-80 sm:w-80"></div>

    <main class="relative w-full max-w-md rounded-2xl border border-white/80 bg-white/90 p-6 shadow-xl shadow-indigo-950/10 backdrop-blur sm:rounded-3xl sm:p-10">
        <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/25 sm:mb-8 sm:h-14 sm:w-14">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.5 2.9 8.5 7 10 4.1-1.5 7-5.5 7-10V6l-7-3Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
            </svg>
        </div>

        <p class="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-indigo-600">Selamat datang</p>
        <h1 class="break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ config('app.name') }}</h1>
        <p class="mt-2 mb-6 text-sm leading-6 text-slate-500 sm:mb-8">Masuk ke akun Anda untuk melanjutkan.</p>

        <form wire:submit="login" class="space-y-4 sm:space-y-5">
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" wire:model="email" type="email" placeholder="nama@email.com" autocomplete="username"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                @error('email') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                <input id="password" wire:model="password" type="password" placeholder="Masukkan password" autocomplete="current-password"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10">
                @error('password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="flex w-full items-center justify-center rounded-xl bg-indigo-600 py-3.5 font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/25 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove>Masuk</span>
                <span wire:loading>Memproses...</span>
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-400 sm:mt-8">Akses aman untuk akun Anda</p>
    </main>
</div>