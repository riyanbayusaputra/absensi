<div wire:ignore x-data="officeMap()" style="display:flex;flex-direction:column;gap:8px">
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <div style="flex:1;min-width:220px">
            <x-filament::input.wrapper>
                <x-filament::input type="text" x-model="query" x-on:keydown.enter.prevent="search()"
                                   placeholder="Cari alamat atau nama tempat, lalu Enter" />
            </x-filament::input.wrapper>
        </div>
        <x-filament::button type="button" color="gray" icon="heroicon-o-magnifying-glass" x-on:click="search()" x-bind:disabled="searching">
            Cari
        </x-filament::button>
        <x-filament::button type="button" color="gray" icon="heroicon-o-map-pin" x-on:click="locate()" x-bind:disabled="locating">
            Lokasi saya
        </x-filament::button>
    </div>

    <div x-show="results.length" x-cloak style="border:1px solid rgba(128,128,128,.35);border-radius:10px;overflow:hidden">
        <template x-for="r in results" :key="r.place_id">
            <button type="button" x-on:click="pick(r)" x-text="r.display_name"
                    style="display:block;width:100%;text-align:left;padding:8px 12px;font-size:13px;border-bottom:1px solid rgba(128,128,128,.2)"></button>
        </template>
    </div>

    <div x-ref="map" style="height:380px;border-radius:12px;z-index:0"></div>

    <p x-show="msg" x-text="msg" style="font-size:13px;opacity:.8"></p>
    <p style="font-size:12px;opacity:.6">
        Klik peta atau geser pin biru ke titik gedung yang tepat. Lingkaran menunjukkan radius absen. Zoom peta untuk presisi lebih baik.
    </p>
</div>