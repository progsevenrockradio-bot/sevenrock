<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $contract->title }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; font-size: 12px; color: #2d3748; padding: 20px; }
        .encabezado { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #c32720; padding-bottom: 15px; }
        h1 { font-size: 18px; color: #1a202c; text-transform: uppercase; margin: 0; }
        .subtitulo { font-size: 10px; color: #718096; margin-top: 5px; font-family: monospace; }
        .datos-partes { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; margin-bottom: 25px; font-size: 11px; }
        .cuerpo-contrato { text-align: justify; margin-bottom: 30px; }
        .cuerpo-contrato p { margin-bottom: 12px; }
        .cuadro-auditoria { border: 2px dashed #c32720; background-color: #edf2f7; padding: 15px; margin-top: 30px; page-break-inside: avoid; }
        .bold { font-weight: bold; color: #1a202c; }
        .info-ip { font-family: monospace; font-size: 11px; background: #ffffff; padding: 10px; border: 1px solid #cbd5e0; margin-top: 8px; line-height: 1.5; }
    </style>
</head>
<body>

    <div class="encabezado">
        @if(!empty($logo_base64))
            <img src="{{ $logo_base64 }}" alt="Logo" style="max-height: 45px; margin-bottom: 12px; display: inline-block;" />
        @endif
        <h1>{{ $contract->title }}</h1>
        <div class="subtitulo">Identificador del Documento: DOC-{{ $contract->token }}</div>
    </div>
    
    <div class="datos-partes">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; padding-bottom: 8px;"><span class="bold">Proveedor:</span> Seven Rock Radio</td>
                <td style="width: 50%; padding-bottom: 8px;"><span class="bold">Banda / Artista:</span> {{ $contract->band_name ?? '-' }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 8px;"><span class="bold">Contacto Proveedor:</span> {{ config('mail.from.address') }}</td>
                <td style="padding-bottom: 8px;"><span class="bold">Representante / Firmante:</span> {{ $nombre }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 8px;"><span class="bold">Fecha de Emisión:</span> {{ $fecha }}</td>
                <td style="padding-bottom: 8px;"><span class="bold">Email Firmante:</span> {{ $contract->signer_email }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 8px;"><span class="bold">Estado:</span> Firmado Electrónicamente</td>
                <td style="padding-bottom: 8px;"><span class="bold">Ubicación de Firma:</span> {{ $contract->city }}, {{ $contract->country }}</td>
            </tr>
        </table>
    </div>

    <div class="cuerpo-contrato">
        {!! $contract->formatted_content !!}
    </div>

    <!-- Bloque de Firmas Visibles -->
    <div style="margin-top: 35px; margin-bottom: 15px; page-break-inside: avoid;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 48%; text-align: center; vertical-align: top; padding: 15px; border: 1px solid #e2e8f0; background: #fafbfc;">
                    <div style="font-weight: bold; font-size: 11px; text-transform: uppercase; color: #1a202c; border-bottom: 1px solid #edf2f7; padding-bottom: 6px; margin-bottom: 12px;">POR SEVEN ROCK RADIO</div>
                    <div style="font-size: 11px; font-family: 'Courier New', monospace; color: #718096; margin-top: 15px; font-style: italic;">[FIRMADO DIGITALMENTE]</div>
                    <div style="font-size: 10px; color: #a0aec0; margin-top: 15px;">Emisora Seven Rock Radio</div>
                </td>
                <td style="width: 4%;">&nbsp;</td>
                <td style="width: 48%; text-align: center; vertical-align: top; padding: 15px; border: 1px solid #e2e8f0; background: #fafbfc;">
                    <div style="font-weight: bold; font-size: 11px; text-transform: uppercase; color: #1a202c; border-bottom: 1px solid #edf2f7; padding-bottom: 6px; margin-bottom: 12px;">POR EL ARTISTA / BANDA</div>
                    <div style="font-size: 18px; font-family: 'Times New Roman', Times, Georgia, serif; font-style: italic; color: #c32720; font-weight: bold; margin-top: 10px; letter-spacing: 1px; display: inline-block;">
                        {{ $nombre }}
                    </div>
                    <div style="font-size: 9px; color: #718096; margin-top: 12px; border-top: 1px dashed #cbd5e0; padding-top: 4px;">Firma Digital Clickwrap Aceptada</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bloque de Auditoría Técnica -->
    <div class="cuadro-auditoria">
        <div class="bold" style="text-transform: uppercase; color: #c32720; margin-bottom: 6px; font-size: 11px;">DECLARACIÓN DE FIRMA ELECTRÓNICA (CLICKWRAP)</div>
        <p style="margin: 0 0 10px 0; font-size: 10px; color: #4a5568; text-align: justify;">
            Al marcar la casilla de verificación y pulsar el botón de firma en la plataforma digital de Seven Rock Radio, el firmante declara su total conformidad, aceptación y adhesión vinculante a cada uno de los términos y condiciones estipulados en este acuerdo legal.
        </p>
        
        <div class="info-ip">
            <span class="bold">Fecha y Hora de Firma (UTC):</span> {{ $fecha_hora }}<br>
            <span class="bold">Dirección IP del Firmante:</span> {{ $ip }}<br>
            <span class="bold">Método de Firma:</span> Aceptación Expresa en Pantalla (Clickwrap)<br>
            <span class="bold">Plataforma de Registro:</span> Emisora Seven Rock Radio Digital System
        </div>
    </div>

</body>
</html>
