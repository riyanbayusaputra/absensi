<div wire:ignore x-data="attendanceMap(@js($payload))" style="font-size:14px">
    <div x-ref="map" style="height:380px;border-radius:12px;z-index:0"></div>

    <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:8px;font-size:12px">
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#4f46e5"></span> Area kantor</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#16a34a"></span> Titik masuk</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dc2626"></span> Titik pulang</span>
        <span style="opacity:.65">Lingkaran tipis di sekitar titik = margin akurasi GPS</span>
    </div>

    <ul style="margin:8px 0 0 18px;list-style:disc;font-size:13px">
        @if ($a->in_lat !== null)
            <li>Masuk {{ $a->check_in_at?->format('H:i') }}: akurasi ± {{ round($a->in_accuracy ?? 0) }} m, {{ $a->in_distance_m }} m dari titik kantor</li>
        @endif
        @if ($a->out_lat !== null)
            <li>Pulang {{ $a->check_out_at?->format('H:i') }}: akurasi ± {{ round($a->out_accuracy ?? 0) }} m, {{ $a->out_distance_m }} m dari titik kantor</li>
        @endif
    </ul>
</div>