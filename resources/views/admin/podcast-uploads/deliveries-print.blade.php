<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entregas y Emisiones — Seven Rock Radio</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Open Sans", DejaVu Sans, Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.35;
            color: #161618;
            background: #ffffff;
            margin: 0;
            padding: 16px;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 14px;
            border: 1px solid #d4d4d8;
            background: #f4f4f5;
            border-radius: 6px;
        }
        .btn {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #27272a;
            color: #18181b;
            background: #ffffff;
        }
        .btn-primary {
            background: #c32720;
            border-color: #c32720;
            color: #ffffff;
        }
        .report-header {
            border-bottom: 2px solid #18181b;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .report-title-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 16px;
        }
        h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #111111;
        }
        .brand-tag {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #c32720;
            margin-bottom: 3px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px 16px;
            margin-top: 8px;
            font-size: 10px;
            color: #3f3f46;
        }
        .meta-box {
            border: 1px solid #e4e4e7;
            background: #fafafa;
            padding: 6px 8px;
            border-radius: 4px;
        }
        .meta-label {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: .05em;
            color: #52525b;
            margin-bottom: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }
        thead {
            display: table-header-group;
        }
        tfoot {
            display: table-footer-group;
        }
        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #d4d4d8;
            padding: 6px 7px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f4f4f5;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #18181b;
        }
        th.col-upload {
            background: #e0f2fe;
            color: #0c4a6e;
        }
        th.col-emission {
            background: #fef3c7;
            color: #78350f;
        }
        td.col-upload {
            background: #f8fcff;
        }
        td.col-emission {
            background: #fffdf5;
        }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        .muted {
            color: #52525b;
            font-size: 9.5px;
        }
        .italic {
            font-style: italic;
            color: #71717a;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
            border: 1px solid #d4d4d8;
            background: #f4f4f5;
            color: #27272a;
        }
        .badge-success {
            background: #ecfdf5;
            border-color: #6ee7b7;
            color: #065f46;
        }
        .badge-warning {
            background: #fffbeb;
            border-color: #fcd34d;
            color: #92400e;
        }
        .badge-danger {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .badge-info {
            background: #f0f9ff;
            border-color: #7dd3fc;
            color: #075985;
        }
        .ref-tag {
            display: inline-block;
            margin-top: 2px;
            padding: 1px 4px;
            border: 1px solid #d97706;
            border-radius: 2px;
            font-size: 8.5px;
            color: #92400e;
            background: #fef3c7;
        }
        .notice-tag {
            display: block;
            margin-top: 3px;
            font-size: 8.5px;
            color: #92400e;
        }
        .notice-authorized {
            color: #075985;
        }
        .footer-note {
            margin-top: 10px;
            font-size: 9.5px;
            color: #52525b;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            th.col-upload, td.col-upload, th.col-emission, td.col-emission, .badge, .meta-box {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div style="font-size: 11px; color: #3f3f46;">
            Vista de impresión completa: incluye <strong>{{ $totalCount }}</strong> {{ $totalCount === 1 ? 'episodio filtrado' : 'episodios filtrados' }} (sin paginación).
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="{{ $backUrl }}" class="btn">← Volver al listado</a>
            <button type="button" onclick="window.print()" class="btn btn-primary">Imprimir / Guardar como PDF</button>
        </div>
    </div>

    <div class="report-header">
        <div class="brand-tag">Seven Rock Radio · {{ $isAdmin ? 'Listado Administrativo' : 'Portal de Conductores' }}</div>
        <div class="report-title-row">
            <h1>Entregas y Emisiones</h1>
            <div class="muted">Total de episodios: <strong>{{ $totalCount }}</strong></div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <div class="meta-label">Filtros aplicados</div>
                <div>{{ count($activeFilterLabels) > 0 ? implode(' · ', $activeFilterLabels) : 'Todos los registros' }}</div>
            </div>
            <div class="meta-box">
                <div class="meta-label">Fecha de generación</div>
                <div class="mono">{{ $generatedAt->format('d/m/Y H:i') }} ({{ $appTimezone }})</div>
            </div>
            <div class="meta-box">
                <div class="meta-label">Zonas horarias</div>
                <div>
                    Subidas: <span class="mono">{{ $appTimezone }}</span>
                    @if (count($programTimezones) > 0)
                        <br>Programas: <span class="mono">{{ implode(', ', $programTimezones) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Programa</th>
                <th style="width: 22%;">Episodio</th>
                <th style="width: 13%;">Subido por</th>
                <th class="col-upload" style="width: 12%;">Subida real</th>
                <th class="col-emission" style="width: 16%;">Emisión prevista</th>
                <th style="width: 10%;">Zona horaria</th>
                <th style="width: 12%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $em = $row['emission'];
                    $dist = $row['distribution'];
                    $rev = $row['revisions'];
                    $badgeClass = match ($dist['tone']) {
                        'success' => 'badge-success',
                        'danger' => 'badge-danger',
                        'warning' => 'badge-warning',
                        'info' => 'badge-info',
                        default => '',
                    };
                @endphp
                <tr>
                    <td><strong>{{ $row['program_name'] }}</strong></td>
                    <td>
                        <span class="mono"><strong>Ep. {{ $row['episode_number'] ?? 'S/N' }}</strong></span> · {{ $row['episode_title'] }}
                    </td>
                    <td>
                        <div class="{{ $row['submitted_by']['registered'] ? '' : 'italic' }}">
                            {{ $row['submitted_by']['name'] }}
                        </div>
                        @if ($rev['has_revisions'])
                            <div class="muted">
                                v{{ $rev['version'] }}{{ $rev['has_audio_substitution'] ? ' · Audio sustituido' : '' }}
                            </div>
                        @endif
                    </td>
                    <td class="col-upload">
                        <div class="mono"><strong>{{ $row['uploaded']['date'] }}</strong></div>
                        @if ($row['uploaded']['time'])
                            <div class="mono muted">{{ $row['uploaded']['time'] }} ({{ $row['uploaded']['timezone'] }})</div>
                        @endif
                    </td>
                    <td class="col-emission">
                        @if ($em['date_missing'])
                            <span class="badge badge-danger">Fecha no registrada</span>
                        @else
                            <div class="mono">
                                <strong>{{ $em['date'] }}</strong>
                                @if ($em['weekday_label'])
                                    <span class="muted">({{ $em['weekday_label'] }})</span>
                                @endif
                            </div>
                            @if (! $em['time_missing'])
                                <div class="mono">{{ $em['time'] }}</div>
                                @if ($em['time_source'] === 'master_current_reference')
                                    <span class="ref-tag">Ref. actual del programa</span>
                                @endif
                            @else
                                <div class="italic">Hora no registrada</div>
                            @endif
                            @if (! empty($em['calendar_notice']))
                                @php $notice = $em['calendar_notice']; @endphp
                                <span class="notice-tag {{ $notice['type'] === 'authorized_manual' ? 'notice-authorized' : '' }}">
                                    {{ $notice['type'] === 'authorized_manual' ? '' : '⚠ ' }}{{ $notice['label'] }}
                                </span>
                            @endif
                        @endif
                    </td>
                    <td class="mono {{ $row['timezone']['program'] ? '' : 'italic' }}">
                        {{ $row['timezone']['program_label'] }}
                    </td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $dist['label'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 18px; color: #71717a;">
                        No se encontraron episodios con los filtros aplicados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-note">
        <span>Los horarios marcados como «Ref. actual del programa» reflejan la parrilla vigente del programa maestro, no un horario histórico confirmado.</span>
        <span>Seven Rock Radio · {{ $generatedAt->format('d/m/Y H:i') }}</span>
    </div>
</body>
</html>

