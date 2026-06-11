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
        <h1>{{ $contract->title }}</h1>
        <div class="subtitulo">Identificador del Documento: DOC-{{ $contract->token }}</div>
    </div>
    
    <div class="datos-partes">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; padding-bottom: 8px;"><span class="bold">Proveedor:</span> Seven Rock Radio</td>
                <td style="width: 50%; padding-bottom: 8px;"><span class="bold">Firmante:</span> {{ $nombre }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 8px;"><span class="bold">Contacto Proveedor:</span> {{ config('mail.from.address') }}</td>
                <td style="padding-bottom: 8px;"><span class="bold">Email Firmante:</span> {{ $contract->signer_email }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 8px;"><span class="bold">Fecha de Emisión:</span> {{ $fecha }}</td>
                <td style="padding-bottom: 8px;"><span class="bold">Ubicación:</span> {{ $contract->city }}, {{ $contract->country }}</td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-bottom: 8px;"><span class="bold">Estado:</span> Firmado Electrónicamente</td>
            </tr>
        </table>
    </div>

    <div class="cuerpo-contrato">
        {!! $contract->formatted_content !!}
    </div>

    <!-- Bloque de Firma y Auditoría Técnica -->
    <div class="cuadro-auditoria">
        <div class="bold" style="text-transform: uppercase; color: #c32720; margin-bottom: 6px; font-size: 12px;">DECLARACIÓN DE FIRMA ELECTRÓNICA (CLICKWRAP)</div>
        <p style="margin: 0 0 10px 0; font-size: 11px; color: #4a5568; text-align: justify;">
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
