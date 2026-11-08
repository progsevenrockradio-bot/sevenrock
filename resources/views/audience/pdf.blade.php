<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page {
            margin: 20mm 16mm 18mm 16mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            color: #222222;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #c32720;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 15pt;
            font-weight: bold;
            color: #0b0b0c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 3px 0;
        }
        .brand-subtitle {
            font-size: 10pt;
            font-weight: 600;
            color: #c32720;
            margin: 0 0 5px 0;
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
        .timezone-alert {
            background-color: #fff8f0;
            border: 1px solid #fed7aa;
            border-left: 3px solid #f97316;
            padding: 5px 8px;
            font-size: 7.5pt;
            color: #9a3412;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        h2 {
            font-size: 10pt;
            font-weight: bold;
            color: #0b0b0c;
            border-left: 3px solid #c32720;
            padding-left: 8px;
            margin: 12px 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .source-tag {
            font-size: 7pt;
            font-weight: normal;
            color: #777777;
            text-transform: none;
            margin-left: 6px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .kpi-table td {
            width: 33.33%;
            padding: 6px 8px;
            background-color: #f8f8f9;
            border: 1px solid #e5e5e7;
            vertical-align: top;
        }
        .kpi-label {
            font-size: 7pt;
            text-transform: uppercase;
            color: #666666;
            margin-bottom: 2px;
            font-weight: 600;
        }
        .kpi-value {
            font-size: 13pt;
            font-weight: bold;
            color: #0b0b0c;
        }
        .kpi-sub {
            font-size: 6.5pt;
            color: #888888;
            margin-top: 1px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 7.5pt;
        }
        .data-table th {
            background-color: #121214;
            color: #ffffff;
            text-align: left;
            padding: 4px 6px;
            font-weight: 600;
            border: 1px solid #121214;
            font-size: 7pt;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 3.5px 6px;
            border: 1px solid #e2e2e5;
        }
        .data-table tr:nth-child(even) td {
            background-color: #fafafb;
        }
        .text-right {
            text-align: right;
        }
        .note-box {
            background-color: #f6f6f8;
            border-left: 2px solid #888888;
            padding: 4px 8px;
            font-size: 6.5pt;
            color: #555555;
            margin-top: 4px;
            margin-bottom: 8px;
            line-height: 1.25;
        }
        .two-col {
            width: 100%;
            border-collapse: collapse;
        }
        .two-col td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .two-col td:first-child {
            padding-right: 6px;
        }
        .two-col td:last-child {
            padding-left: 6px;
        }
        .footer {
            margin-top: 12px;
            border-top: 1px solid #dcdcdc;
            padding-top: 6px;
            font-size: 6.5pt;
            color: #777777;
            text-align: center;
            line-height: 1.3;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="brand-title">SEVEN ROCK RADIO</div>
        <div class="brand-subtitle">{{ $report['title'] }}</div>
        <div class="meta-bar">
            <span><strong>Período:</strong> {{ $report['period']['label'] }}</span>
            <span><strong>Identificador:</strong> {{ $report['id'] }}</span>
            <span><strong>Generado:</strong> {{ \Carbon\Carbon::parse($report['generated_at'])->format('d/m/Y H:i:s') }} (Europe/Madrid)</span>
        </div>
    </div>

    <div class="timezone-alert">
        <strong>Aviso metodológico sobre zonas horarias:</strong> {{ $report['metadata']['timezone_note'] }}
    </div>

    <!-- BLOQUE 1: RADIO STREAMING -->
    <h2>1. Emisión de Radio en Streaming <span class="source-tag">Fuente: {{ $report['radioboss']['source'] }} | Zona horaria: {{ $report['radioboss']['timezone'] }}</span></h2>

    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-label">Horas de escucha (TLH)</div>
                <div class="kpi-value">{{ number_format($report['radioboss']['total_tlh'], 0, ',', '.') }} h</div>
                <div class="kpi-sub">Total Listening Hours acumuladas</div>
            </td>
            <td>
                <div class="kpi-label">Sesiones de streaming</div>
                <div class="kpi-value">{{ number_format($report['radioboss']['total_sessions'], 0, ',', '.') }}</div>
                <div class="kpi-sub">Conexiones al servidor de emisión</div>
            </td>
            <td>
                <div class="kpi-label">Oyentes únicos (Suma diaria)</div>
                <div class="kpi-value">{{ number_format($report['radioboss']['total_unique'], 0, ',', '.') }}</div>
                <div class="kpi-sub">Suma aritmética de únicos diarios</div>
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha (Madrid)</th>
                            <th class="text-right">Sesiones</th>
                            <th class="text-right">Únicos día</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['radioboss']['days'] as $day)
                            <tr>
                                <td>{{ $day['date'] }}</td>
                                <td class="text-right">{{ number_format($day['cnt'], 0, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($day['cnt_unique'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>País</th>
                            <th class="text-right">Horas (TLH)</th>
                            <th class="text-right">Sesiones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $tlhMap = [];
                            foreach($report['radioboss']['countries_tlh'] ?? [] as $ct) {
                                $tlhMap[$ct['country']] = $ct['tlh'];
                            }
                        @endphp
                        @foreach(array_slice($report['radioboss']['countries'] ?? [], 0, 7) as $country)
                            <tr>
                                <td>{{ $country['country'] }} ({{ strtoupper($country['country_iso']) }})</td>
                                <td class="text-right">{{ number_format($tlhMap[$country['country']] ?? 0, 0, ',', '.') }} h</td>
                                <td class="text-right">{{ number_format($country['cnt'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="note-box">
        <strong>Nota metodológica RadioBOSS:</strong> {{ $report['radioboss']['methodology_note'] }}
    </div>

    <!-- BLOQUE 2: TRAFICO WEB -->
    <h2>2. Tráfico en el Portal Web <span class="source-tag">Fuente: {{ $report['cloudflare']['source'] }} | Zona horaria: {{ $report['cloudflare']['timezone'] }}</span></h2>

    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-label">Páginas vistas (pageViews)</div>
                <div class="kpi-value">{{ number_format($report['cloudflare']['page_views'], 0, ',', '.') }}</div>
                <div class="kpi-sub">Documentos HTML servidos con éxito (HTTP 200)</div>
            </td>
            <td>
                <div class="kpi-label">Visitantes únicos semanales</div>
                <div class="kpi-value">{{ number_format($report['cloudflare']['unique_visitors'], 0, ',', '.') }}</div>
                <div class="kpi-sub">Estimación agregada del período por Cloudflare</div>
            </td>
            <td>
                <div class="kpi-label">Peticiones perimetrales (requests)</div>
                <div class="kpi-value">{{ number_format($report['cloudflare']['requests'], 0, ',', '.') }}</div>
                <div class="kpi-sub">{{ round($report['cloudflare']['bytes'] / 1073741824, 2) }} GB transferidos</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha (UTC)</th>
                <th class="text-right">Páginas vistas</th>
                <th class="text-right">Visitantes únicos día</th>
                <th class="text-right">Peticiones totales</th>
                <th class="text-right">Amenazas bloqueadas</th>
                <th class="text-right">Tráfico transferido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['cloudflare']['days'] as $cfDay)
                <tr>
                    <td>{{ $cfDay['date'] }}</td>
                    <td class="text-right">{{ number_format($cfDay['page_views'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($cfDay['uniques'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($cfDay['requests'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($cfDay['threats'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ round($cfDay['bytes'] / 1048576, 1) }} MB</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="note-box">
        <strong>Nota metodológica Cloudflare:</strong> {{ $report['cloudflare']['methodology_note'] }}
    </div>

    <!-- PIE Y DISCLAIMER -->
    <div class="footer">
        {{ $report['metadata']['disclaimer'] }}<br>
        Documento oficial informativo generado automáticamente por Seven Rock Radio. Registro de archivo: {{ $report['id'] }}.
    </div>

</body>
</html>
