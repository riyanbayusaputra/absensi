<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="description" content="{{ $description ?? 'Aplikasi absensi karyawan PT sakamedia.' }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? 'Aplikasi absensi karyawan PT sakamedia.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
    <meta name="twitter:description" content="{{ $description ?? 'Aplikasi absensi karyawan PT sakamedia.' }}">
    
    <link rel="icon" type="image/jpg" sizes="32x32" href="{{ asset('images/saka.jpg') }}">
    <link rel="icon" type="image/jpg" href="{{ asset('images/saka.jpg') }}">
<link rel="shortcut icon" type="image/png" href="{{ asset('images/saka.jpg') }}">
<link rel="apple-touch-icon" href="{{ asset('images/saka.jpg') }}">

    {{-- Tailwind via CDN: tanpa Vite, tanpa npm. data-navigate-once agar tidak dimuat ulang saat wire:navigate --}}
    <script data-navigate-once src="https://cdn.tailwindcss.com/3.4.17?plugins=forms"></script>
    <style>[x-cloak]{display:none!important}</style>

    @stack('head')
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    <div class="mx-auto min-h-screen max-w-md bg-slate-50 pb-20 shadow-xl">
        {{ $slot }}
    </div>

    @auth
    <nav aria-label="Navigasi utama" class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-md border-t border-slate-200/80 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur">
        <div class="grid grid-cols-4 px-2 text-center">
            <a wire:navigate href="{{ route('app.home') }}" aria-current="{{ request()->routeIs('app.home') ? 'page' : 'false' }}"
               class="flex flex-col items-center gap-1 py-2 text-[11px] transition {{ request()->routeIs('app.home') ? 'font-semibold text-indigo-600' : 'text-slate-500 hover:text-indigo-600' }}">
                <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                <span>Beranda</span>
            </a>
            <a wire:navigate href="{{ route('app.attend') }}" aria-current="{{ request()->routeIs('app.attend') ? 'page' : 'false' }}"
               class="flex flex-col items-center gap-1 py-2 text-[11px] transition {{ request()->routeIs('app.attend') ? 'font-semibold text-indigo-600' : 'text-slate-500 hover:text-indigo-600' }}">
                <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18m-13 5 2 2 4-4"/></svg>
                <span>Absen</span>
            </a>
            <a wire:navigate href="{{ route('app.history') }}" aria-current="{{ request()->routeIs('app.history') ? 'page' : 'false' }}"
               class="flex flex-col items-center gap-1 py-2 text-[11px] transition {{ request()->routeIs('app.history') ? 'font-semibold text-indigo-600' : 'text-slate-500 hover:text-indigo-600' }}">
                <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/><path d="M5 3 3 5m16-2 2 2"/></svg>
                <span>Riwayat</span>
            </a>
            <a wire:navigate href="{{ route('app.profile') }}" aria-current="{{ request()->routeIs('app.profile*') ? 'page' : 'false' }}"
               class="flex flex-col items-center gap-1 py-2 text-[11px] transition {{ request()->routeIs('app.profile*') ? 'font-semibold text-indigo-600' : 'text-slate-500 hover:text-indigo-600' }}">
                <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                <span>profile</span>
            </a>
        </div>
    </nav>
    @endauth
     @stack('scripts')
    @livewireScripts
</body>
</html>