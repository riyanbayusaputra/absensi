<div x-data="{ busy: false, msg: '' }" class="flex items-center gap-3">
    <x-filament::button
        type="button"
        color="gray"
        icon="heroicon-o-map-pin"
        x-bind:disabled="busy"
        x-on:click="
            if (! navigator.geolocation) { msg = 'Browser tidak mendukung GPS.'; return; }
            busy = true; msg = 'Mencari lokasi...';
            navigator.geolocation.getCurrentPosition(
                (p) => {
                    $wire.set('data.latitude', p.coords.latitude.toFixed(7), false);
                    $wire.set('data.longitude', p.coords.longitude.toFixed(7), false);
                    msg = 'Terisi. Akurasi ± ' + Math.round(p.coords.accuracy) + ' m';
                    busy = false;
                },
                () => { msg = 'Gagal mengambil lokasi. Izinkan akses lokasi di browser.'; busy = false; },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            )
        "
    >
        Ambil lokasi saya
    </x-filament::button>

    <span x-text="msg" class="text-sm text-gray-500"></span>
</div>