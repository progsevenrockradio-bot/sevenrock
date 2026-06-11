@php
    $theme = \App\Models\ThemeSetting::current();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma de Contrato Requerida</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0c0c0e; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; -webkit-text-size-adjust: none; width: 100% !important;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0c0c0e; padding: 40px 10px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #141416; border: 1px solid #28282b; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
                    <!-- Header with Logo -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 20px 40px; border-bottom: 1px solid #222225;">
                            @if(!empty($theme->logo_url))
                                <img src="{{ $theme->logo_url }}" alt="Seven Rock Radio" style="max-height: 55px; width: auto; display: block;" />
                            @else
                                <span style="font-size: 22px; font-weight: bold; letter-spacing: 0.15em; text-transform: uppercase; color: #ffffff;">Seven Rock Radio</span>
                            @endif
                            <div style="font-size: 10px; color: #88888f; text-transform: uppercase; letter-spacing: 0.25em; margin-top: 12px; font-weight: 600;">Portal de Firma Digital</div>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 700; line-height: 1.3; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; border-left: 3px solid #c32720; padding-left: 14px;">
                                Firma de Contrato Requerida
                            </h1>
                            
                            <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.7; color: #cccccc;">
                                Hola, <strong style="color: #ffffff;">{{ $contract->signer_name }}</strong>:
                            </p>
                            
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #cccccc;">
                                Se ha preparado un documento legal titulado <strong style="color: #ffffff;">«{{ $contract->title }}»</strong> para formalizar tu acuerdo de colaboración con Seven Rock Radio.
                            </p>
                            
                            <!-- Summary Box -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #1a1a1e; border: 1px solid #2d2d33; border-radius: 8px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h2 style="margin: 0 0 12px 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #88888f;">Resumen del Acuerdo</h2>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 14px; line-height: 1.5; color: #cccccc;">
                                            <tr>
                                                <td width="35%" style="padding: 4px 0; color: #88888f; vertical-align: top;">Documento:</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff; font-weight: 500;">{{ $contract->title }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; color: #88888f; vertical-align: top;">Destinatario:</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff;">{{ $contract->signer_name }} ({{ $contract->signer_email }})</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; color: #88888f; vertical-align: top;">Modalidad:</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff; font-family: monospace; font-size: 12px;">E-Signature Clickwrap</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.7; color: #aaaaaa; text-align: center;">
                                Por favor, accede al portal haciendo clic en el siguiente botón para revisar las cláusulas contractuales detalladas y proceder con la firma electrónica vinculante:
                            </p>
                            
                            <!-- Call to Action Button -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 30px;">
                                        <a href="{{ $signingUrl }}" target="_blank" style="background-color: #c32720; color: #ffffff; text-decoration: none; padding: 14px 36px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.12em; font-size: 13px; border-radius: 30px; display: inline-block; box-shadow: 0 6px 20px rgba(195,39,32,0.4); border: 1px solid #d42c24; transition: all 0.3s ease;">
                                            Revisar y Firmar Contrato 🖋
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Backup Link -->
                            <p style="margin: 0; font-size: 12px; line-height: 1.6; color: #88888f; border-top: 1px solid #222225; padding-top: 20px;">
                                Si el botón no funciona, copia y pega la siguiente dirección URL en tu navegador:<br>
                                <a href="{{ $signingUrl }}" target="_blank" style="color: #d3a15a; text-decoration: none; word-break: break-all; display: block; margin-top: 5px;">{{ $signingUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #0d0d0f; border-top: 1px solid #222225; font-size: 11px; line-height: 1.8; color: #66666a; text-align: center;">
                            <p style="margin: 0 0 6px 0;">Este es un mensaje automático generado por el sistema de firma de Seven Rock Radio.</p>
                            <p style="margin: 0;">© {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
