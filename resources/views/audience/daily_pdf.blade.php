<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page {
            margin: 22mm 18mm 20mm 18mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #222222;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #c32720;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .brand-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0b0b0c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 3px 0;
        }
        .brand-subtitle {
            font-size: 11pt;
            font-weight: 600;
            color: #c32720;
            margin: 0 0 6px 0;
        }
        .meta-bar {
            font-size: 8pt;
            color: #666666;
        }
        .meta-bar span {
            margin-right: 14px;
        }
        .meta-bar strong {
            color: #333333;
        }
        .disclaimer-alert {
            background-color: #fff8f0;
            border: 1px solid #fed7aa;
            border-left: 3px solid #f97316;
            padding: 8px 10px;
            font-size: 8pt;
            color: #9a3412;
            margin-bottom: 16px;
            line-height: 1.35;
        }
        .disclaimer-alert strong {
            display: block;
            margin-bottom: 2px;
            font-size: 8.5pt;
        }
        h2 {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0b0b0c;
            border-left: 3px solid #c32720;
            padding-left: 8px;
            margin: 16px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .kpi-table td {
            width: 33.33%;
            padding: 8px 10px;
            background-color: #f8f8f9;
            border: 1px solid #e5e5e7;
            vertical-align: top;
        }
        .kpi-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #666666;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .kpi-value {
            font-size: 15pt;
            font-weight: bold;
            color: #0b0b0c;
        }
        .kpi-sub {
            font-size: 7pt;
            color: #888888;
            margin-top: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 8pt;
        }
        .data-table th {
            background-color: #121214;
            color: #ffffff;
            text-align: left;
            padding: 5px 8px;
            font-weight: 600;
            border: 1px solid #121214;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 5px 8px;
            border: 1px solid #e5e5e7;
            color: #333333;
        }
        .data-table tr:nth-child(even) td {
            background-color: #fafafa;
        }
        .text-right {
            text-align: right;
        }
        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }
        .footer {
            margin-top: 25px;
            border-top: 1px solid #e5e5e7;
            padding-top: 8px;
            font-size: 7pt;
            color: #888888;
            line-height: 1.35;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="brand-title">SEVEN ROCK RADIO</div>
        <div class="brand-subtitle">Informe Oficial de Audiencia Diaria de Radio</div>
        <div class="meta-bar">
            <span><strong>Fecha:</strong> {{ $report['formatted_date'] }}</span>
            <span><strong>Identificador:</strong> {{ $report['id'] }}</span>
            <span><strong>Generado:</strong> {{ \Carbon\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }} (Europe/Madrid)</span>
            <span><strong>Fuente:</strong> {{ $report['radioboss']['source'] }}</span>
        </div>
    </div>

    {{-- AVISO OBLIGATORIO --}}
    <div class="disclaimer-alert">
        <strong>Aviso informativo sobre el alcance del informe:</strong>
        Audiencia global de Seven Rock Radio durante el día seleccionado. No corresponde exclusivamente a un programa.
    </div>

    <h2>Métricas Principales de Sintonización (RadioBOSS)</h2>
    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-label">Sesiones de streaming</div>
                <div class="kpi-value">{{ number_format($report['radioboss']['total_sessions'], 0, ',', '.') }}</div>
                <div class="kpi-sub">Conexiones totales al stream iniciadas</div>
            </td>
            <td>
                <div class="kpi-label">Horas de escucha (TLH)</div>
                <div class="kpi-value">{{ number_format($report['radioboss']['total_tlh'], 0, ',', '.') }} h</div>
                <div class="kpi-sub">Total Listening Hours acumuladas</div>
            </td>
            <td>
                <div class="kpi-label">Oyentes únicos diarios</div>
                <div class="kpi-value">{{ number_format($report['radioboss']['total_unique'], 0, ',', '.') }}</div>
                <div class="kpi-sub">Oyentes únicos registrados en el día</div>
            </td>
        </tr>
    </table>

    <h2>Distribución Geográfica de la Emisión</h2>
    @php
        $tlhList = $report['radioboss']['countries_tlh'] ?? [];
        $sessionsMap = collect($report['radioboss']['countries'] ?? [])->keyBy('country_iso');
        $uniquesMap = collect($report['radioboss']['countries_unique'] ?? [])->keyBy('country_iso');
    @endphp

    @if(empty($tlhList))
        <p style="font-size: 8pt; color: #888;">No hay desglose por países registrado para esta jornada.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>País</th>
                    <th>Código ISO</th>
                    <th class="text-right">Horas de Escucha (TLH)</th>
                    <th class="text-right">Sesiones</th>
                    <th class="text-right">Oyentes Únicos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tlhList as $c)
                    @php
                        $iso = $c['country_iso'] ?? '';
                        $sess = $sessionsMap->get($iso)['cnt'] ?? null;
                        $uniq = $uniquesMap->get($iso)['uniq'] ?? null;
                    @endphp
                    <tr>
                        <td><strong>{{ $c['country'] ?? 'Desconocido' }}</strong></td>
                        <td class="font-mono">{{ strtoupper($iso) }}</td>
                        <td class="text-right font-mono">{{ number_format($c['tlh'] ?? 0, 0, ',', '.') }} h</td>
                        <td class="text-right font-mono">{{ $sess !== null ? number_format($sess, 0, ',', '.') : '—' }}</td>
                        <td class="text-right font-mono">{{ $uniq !== null ? number_format($uniq, 0, ',', '.') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <div><strong>Seven Rock Radio</strong> · Documento oficial informativo para consulta pública y de conductores.</div>
        <div>Audiencia global de Seven Rock Radio durante el día seleccionado. No corresponde exclusivamente a un programa.</div>
        <div>Datos registrados en horario oficial de emisión (Europe/Madrid). Registro de archivo: {{ $report['id'] }}.</div>
    </div>

</body>
</html>

