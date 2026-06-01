@php
    $admin = $themeAppearance['admin_texts'] ?? [];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manual de podcast uploads - {{ $themeSettings->site_name }}</title>
    <style>
        @page { margin: 16px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f1f1f; line-height: 1.35; }
        h1, h2, h3 { margin: 0; }
        .muted { color: #666; }
        .header { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
        .panel { border: 1px solid #bbb; padding: 10px; margin-bottom: 10px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { border: 1px solid #ccc; vertical-align: top; padding: 7px; width: 50%; }
        .table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .table th, .table td { border: 1px solid #cfcfcf; padding: 5px 6px; text-align: left; vertical-align: top; }
        .table th { background: #efefef; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; }
        .page-break { page-break-after: always; }
        .small { font-size: 9px; }
        ul { margin: 5px 0 0 18px; padding: 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Seven Rock Radio - Manual de podcast uploads</h1>
        <div class="muted">Exportado el {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="panel">
        <strong>{{ $themeSettings->site_name }}</strong>
        <div class="muted" style="margin-top: 6px;">
            Guía breve de la pantalla <strong>/admin/podcast-uploads</strong> con enfoque de uso operativo.
        </div>
    </div>

    <div class="panel">
        <h2>Índice</h2>
        <table class="grid" style="margin-top: 8px;">
            <tr>
                <td><strong>1. Datos editoriales</strong><br><span class="muted">Programa, fecha y título</span></td>
                <td><strong>2. Multimedia pesada</strong><br><span class="muted">MP3 y carátula</span></td>
            </tr>
            <tr>
                <td><strong>3. Distribución técnica</strong><br><span class="muted">Copia y sincronización</span></td>
                <td><strong>4. Últimos episodios</strong><br><span class="muted">Lista, reintentos, descargas y borrado.</span></td>
            </tr>
        </table>
    </div>

    <div class="panel">
        <h2>Resumen ejecutivo</h2>
        <ul>
            <li><strong>Datos editoriales:</strong> define el programa, fecha, título y resumen del episodio.</li>
            <li><strong>Multimedia pesada:</strong> el MP3 manda y la carátula puede venir por URL o archivo.</li>
            <li><strong>Distribución técnica:</strong> conserva copia local y activa Archive.org si aplica.</li>
            <li><strong>Estado:</strong> no se edita manualmente, depende del pipeline.</li>
        </ul>
    </div>

    <div class="page-break"></div>

    <div class="panel">
        <h2>Campos clave por bloque</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Bloque</th>
                    <th>Campos</th>
                    <th>Riesgo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Datos editoriales</td>
                    <td>master_program_id, numero_episodio, live_title, fecha_emision, biografia_invitado, resena</td>
                    <td>Medio</td>
                </tr>
                <tr>
                    <td>Multimedia pesada</td>
                    <td>archivo_mp3, imagen_episodio_url, imagen_episodio_file</td>
                    <td>Alto en MP3, medio en imagen</td>
                </tr>
                <tr>
                    <td>Distribución técnica</td>
                    <td>download_processed_mp3, sync_archive_org</td>
                    <td>Medio</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>Notas rápidas</h2>
        <div class="small muted">
            Si el formulario falla, revisa primero la pestaña señalada por los badges del panel.
            El estado final del episodio se calcula automáticamente y no se debe forzar desde esta pantalla.
            El PDF se mantiene compacto para lectura rápida e impresión directa.
            Subtítulos visuales: Programa, fecha y título | MP3 y carátula | Copia y sincronización.
        </div>
    </div>
</body>
</html>
