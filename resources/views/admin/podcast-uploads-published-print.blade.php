<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Publicados - Seven Rock Radio</title>
    <style>
        @page { margin: 16px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1d1d1d; }
        h1, h2 { margin: 0; }
        .header { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .muted { color: #666; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .button { display: inline-block; padding: 8px 12px; border: 1px solid #222; text-decoration: none; color: #111; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #ccc; padding: 6px 7px; text-align: left; vertical-align: top; }
        .table th { background: #efefef; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; }
        .badge { display: inline-block; padding: 2px 6px; border: 1px solid #aaa; border-radius: 3px; font-size: 9px; text-transform: uppercase; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.print();
        });
    </script>
</head>
<body>
    <div class="header">
        <h1>Seven Rock Radio - Últimos publicados</h1>
        <div class="muted">Consulta rápida de los últimos episodios con entrega verificada.</div>
    </div>

    <div class="toolbar no-print">
        <a href="{{ route('admin.podcast-uploads.published') }}" class="button">Ver pantalla</a>
        <a href="{{ route('admin.podcast-uploads.index') }}" class="button">Volver a uploads</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Título</th>
                <th>Programa</th>
                <th>Fecha</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentPublishedUploads as $upload)
                <tr>
                    <td>{{ $upload->numero_episodio }}</td>
                    <td>{{ $upload->live_title ?: $upload->titulo_programa }}</td>
                    <td>{{ $upload->masterProgram?->name ?? 'Sin programa maestro' }}</td>
                    <td>{{ optional($upload->fecha_emision)->format('d/m/Y') }}</td>
                    <td><span class="badge">Publicado</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No hay episodios publicados para mostrar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 10px;" class="muted">
        Se muestran los últimos {{ $recentPublishedUploads->count() }} publicados.
    </div>
</body>
</html>
