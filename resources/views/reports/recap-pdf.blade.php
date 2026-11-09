@php($colors = \App\Services\AttendanceReport::COLORS)
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #111; }
    h1 { font-size: 13px; margin: 0; }
    p { margin: 2px 0; }
    .muted { color: #666; }
    table { border-collapse: collapse; width: 100%; margin-top: 8px; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th, td { border: 1px solid #bbb; padding: 2px 1px; text-align: center; }
    th { background: #eee; }
    td.l { text-align: left; padding-left: 3px; white-space: nowrap; }
    .legend span { display: inline-block; margin-right: 8px; padding: 1px 4px; }
</style>
</head>
<body>
    <h1>{{ $company }}</h1>
    <p>Rekap Absensi Karyawan &middot; {{ $data['from']->translatedFormat('F Y') }}</p>
    <p class="muted">Dicetak {{ now()->translatedFormat('d M Y H:i') }} &middot; {{ count($data['rows']) }} karyawan</p>

    <table>
        <thead>
            <tr>
                <th>No</th><th>Nama</th><th>Dept</th>
                @foreach ($data['dates'] as $d)
                    <th style="{{ $d->isWeekend() ? 'background:#ddd' : '' }}">{{ $d->day }}</th>
                @endforeach
                <th>H</th><th>T</th><th>A</th><th>C</th><th>S</th><th>I</th><th>K</th><th>Telat<br>(mnt)</th><th>%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['rows'] as $i => $r)
                @php($s = $r['sum'])
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="l">{{ $r['employee']->name }}</td>
                    <td class="l">{{ $r['employee']->department?->name }}</td>
                    @foreach ($data['dates'] as $d)
                        @php($code = $r['cells'][$d->toDateString()] ?? '')
                        @php($c = $colors[$code] ?? null)
                        <td style="{{ $c ? "background:{$c[0]};color:{$c[1]}" : '' }}">{{ $code }}</td>
                    @endforeach
                    <td>{{ $s['hadir'] }}</td><td>{{ $s['terlambat'] }}</td><td>{{ $s['alfa'] }}</td>
                    <td>{{ $s['cuti'] }}</td><td>{{ $s['sakit'] }}</td><td>{{ $s['izin'] }}</td><td>{{ $s['khusus'] }}</td>
                    <td>{{ $s['late_min'] }}</td><td>{{ $s['pct'] !== null ? $s['pct'] : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="legend" style="margin-top:8px">
        @foreach (\App\Services\AttendanceReport::LABELS as $code => $label)
            <span style="background:{{ $colors[$code][0] }};color:{{ $colors[$code][1] }}">{{ $code }} = {{ $label }}</span>
        @endforeach
    </p>
</body>
</html>