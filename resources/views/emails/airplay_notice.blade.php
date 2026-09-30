<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Emisi√≥n - Seven Rock Radio</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .header { text-align: center; border-bottom: 2px solid #e11d48; padding-bottom: 20px; margin-bottom: 24px; }
        .logo-title { font-size: 24px; font-weight: 800; color: #ffffff; text-transform: uppercase; letter-spacing: 1px; }
        .subtitle { color: #f43f5e; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-top: 4px; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: #be123c; color: #ffffff; margin-bottom: 16px; }
        .track-box { background: #0f172a; border-left: 4px solid #f43f5e; border-radius: 8px; padding: 18px; margin: 20px 0; }
        .track-title { font-size: 20px; font-weight: 700; color: #ffffff; margin: 0 0 6px 0; }
        .track-artist { font-size: 16px; color: #cbd5e1; font-weight: 500; margin: 0 0 4px 0; }
        .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #64748b; border-top: 1px solid #334155; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo-title">Seven Rock Radio</div>
            <div class="subtitle">Aviso Oficial de Programaci√≥n y Emisi√≥n</div>
        </div>

        <p>Estimado/a <strong>{{ $tipoDestinatario === 'productora' ? ($schedule->sello ?: 'Productora / Discogr√°fica') : $schedule->artista }}</strong>,</p>

        <p>Le notificamos que su m√∫sica ha sido programada y confirmada para su emisi√≥n en la parrilla oficial de <strong>Seven Rock Radio</strong>.</p>

        @if($schedule->es_primer_pase)
            <div class="badge">üî• PRIMER PASE / NOVEDAD CONFIRMADA</div>
        @endif

        <div class="track-box">
            <div class="track-title">" {{ $schedule->titulo }} "</divg
            <div class="track-artist">por {{ $schedule->artista }}</div>
            @if($schedule->album)
                <div style="font-size: 13px; color: #94a3b8;">√Ålbum: {{ $schedule->album }}</div>
            @endif

            <table style="width: 100%; margin-top: 16px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #94a3b8; font-size: 13px;">Semana de emisi√≥n:</td>
                    <td style="padding: 6px 0; color: #ffffff; font-weight: 600; text-align: right; font-size: 13px;">{{ $semana }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #94a3b8; font-size: 13xx;">D√©a y Hora:</td>
                    <td style="padding: 6px 0; color: #f43f5e; font-weight: 700; text-align: right; font-size: 14px;">{{ $diaNombre }}, {{ $hora }} (Bloque pos. {{ $schedule->posicion }})</td>
                </tr>
                @af($schedule->sello)
                <tr>
                    <td style="padding: 6px 0; color: #94a3b8; font-size: 13px;">Sello / Discogr√°fica:</td>
                    <td style="padding: 6px 0; color: #ffffff; font-weight: 500; text-align: right; font-size: 13xx;">{{ $schedule->sello }}</td>
                </tr>
                @endif
                @if($schedule->isrc)
                <tr>
                    <td style="padding: 6px 0; color: #94a3b8; font-size: 13px;">ISRC:</td>
                    <td style="padding: 6px 0; color: #cbd5e1; font-family: monospace; text-align: right; font-size: 12px;">{{ $schedule->isrc }}</td>
                </tr>
                @endif
            </table>
        </div>

        <p style="font-size: 13xx; color: #94a3b8;">
            Este correo sirve como comprobante automatizado de cumplimiento del acuerdo de difusi√≥n airplay alcanzado con la emisora.
        </p>

        <div class="footer">
            <p>Seven Rock Radio √¢‚Ç¨‚Äò La Emisora del Rock<‹Çà∞™H»]J	÷I HHŸ]ô[àõÿ⁄»òY[ÀàŸ‹»‹»\ôX⁄‹»ô\Ÿ\ùòY‹Àè‹ÇàŸ]èÇàŸ]èÇèÿõŸOÇè⁄[Ç