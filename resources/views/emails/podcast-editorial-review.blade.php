<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión Editorial de Episodio</title>
</head>
<body style="font-family: 'Open Sans', system-ui, -apple-system, sans-serif; background-color: #151515; color: #dcdcdc; padding: 24px; margin: 0;">
    <div style="max-width: 640px; margin: 0 auto; background-color: #101012; border: 1px solid #2b2b2b; padding: 28px;">
        <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; color: #c32720; margin: 0 0 8px 0;">
            Seven Rock Radio · Control Editorial (Versión v{{ $version }})
        </p>
        <h1 style="font-size: 22px; color: #ffffff; margin: 0 0 18px 0;">
            {{ $programName }} — Episodio #{{ $episode->numero_episodio }}
        </h1>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
            <tr>
                <td style="padding: 6px 0; color: #8b8b8b; width: 160px;">Título del episodio:</td>
                <td style="padding: 6px 0; color: #f0f0f0; font-weight: bold;">{{ $episode->live_title }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #8b8b8b;">Fecha de emisión:</td>
                <td style="padding: 6px 0; color: #f0f0f0;">{{ optional($episode->fecha_emision)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #8b8b8b;">Autor de la entrega:</td>
                <td style="padding: 6px 0; color: #f0f0f0;">{{ $submitterName }} ({{ $submitterEmail }})</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #8b8b8b;">Versión editorial:</td>
                <td style="padding: 6px 0; color: #f0f0f0;">v{{ $version }}</td>
            </tr>
        </table>

        <div style="background-color: #18181b; border: 1px solid #27272a; padding: 14px; margin-bottom: 20px;">
            <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.16em; color: #9a9a9a; margin: 0 0 6px 0;">Resumen de la entrega</p>
            <p style="font-size: 14px; line-height: 1.6; color: #e4e4e7; margin: 0;">{{ $summaryText ?: 'Sin resumen especificado.' }}</p>
        </div>

        @if ($coverPreviewUrl !== '')
            <div style="margin-bottom: 20px;">
                <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.16em; color: #9a9a9a; margin: 0 0 8px 0;">Vista previa de portada</p>
                <img src="{{ $coverPreviewUrl }}" alt="Vista previa de portada" style="max-width: 220px; height: auto; border: 1px solid #2b2b2b; display: block;">
                <p style="font-size: 12px; color: #7b7b7b; margin: 4px 0 0 0;">Referencia: {{ $coverPreviewUrl }}</p>
            </div>
        @endif

        @if (!empty($objectiveIssues) || !empty($editorialItems))
            <div style="background-color: rgba(195, 39, 32, 0.12); border: 1px solid #7a2b2b; padding: 14px; margin-bottom: 20px;">
                <p style="font-size: 12px; font-weight: bold; text-transform: uppercase; color: #ffd3d3; margin: 0 0 8px 0;">
                    Advertencias y observaciones pertinentes
                </p>
                @if (!empty($objectiveIssues))
                    <p style="font-size: 12px; color: #fca5a5; margin: 0 0 4px 0; font-weight: bold;">Comprobaciones objetivas:</p>
                    <ul style="margin: 0 0 10px 0; padding-left: 18px; color: #fecaca; font-size: 13px;">
                        @foreach ($objectiveIssues as $issue)
                            <li>{{ $issue['message'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (!empty($editorialItems))
                    <p style="font-size: 12px; color: #fde68a; margin: 0 0 4px 0; font-weight: bold;">Recomendaciones editoriales (requieren criterio humano):</p>
                    <ul style="margin: 0; padding-left: 18px; color: #fef08a; font-size: 13px;">
                        @foreach ($editorialItems as $item)
                            <li>{{ $item['message'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @else
            <div style="background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.35); padding: 12px; margin-bottom: 20px; font-size: 13px; color: #a7f3d0;">
                Sin advertencias objetivas ni observaciones editoriales pendientes.
            </div>
        @endif

        <div style="margin-top: 24px;">
            <a href="{{ $reviewUrl }}" style="display: inline-block; padding: 12px 22px; background-color: #27272a; color: #ffffff; text-decoration: none; font-weight: bold; border: 1px solid #3f3f46; margin-right: 12px;">
                Revisar
            </a>
            <a href="{{ $confirmApproveUrl }}" style="display: inline-block; padding: 12px 22px; background-color: #c32720; color: #ffffff; text-decoration: none; font-weight: bold;">
                Aprobar
            </a>
        </div>

        <p style="font-size: 12px; color: #7b7b7b; margin-top: 20px; line-height: 1.5;">
            Nota de seguridad: Abrir el enlace «Aprobar» muestra una pantalla de confirmación segura para la versión <strong>v{{ $version }}</strong> y no ejecuta la aprobación mediante GET.
        </p>
    </div>
</body>
</html>

