@php
    $theme = \App\Models\ThemeSetting::current();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Firmado con Éxito</title>
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
                            <div style="font-size: 10px; color: #88888f; text-transform: uppercase; letter-spacing: 0.25em; margin-top: 12px; font-weight: 600;">Contrato Formalizado</div>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 700; line-height: 1.3; color: #2ecc71; text-transform: uppercase; letter-spacing: 0.05em; border-left: 3px solid #2ecc71; padding-left: 14px;">
                                Contrato Firmado con Éxito
                            </h1>
                            
                            <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.7; color: #cccccc;">
                                Hola, <strong style="color: #ffffff;">{{ $contract->signer_name }}</strong>:
                            </p>
                            
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #cccccc;">
                                Te confirmamos que el contrato titulado <strong style="color: #ffffff;">«{{ $contract->title }}»</strong> ha sido firmado de forma electrónica y formalizado exitosamente.
                            </p>
                            
                            <!-- Summary Box -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #1a1a1e; border: 1px solid #2d2d33; border-radius: 8px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h2 style="margin: 0 0 12px 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #88888f;">Datos de Auditoría de la Firma</h2>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 14px; line-height: 1.6; color: #cccccc;">
                                            <tr>
                                                <td width="35%" style="padding: 4px 0; color: #88888f; vertical-align: top;">Firmante:</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff; font-weight: 500;">{{ $contract->signer_name }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; color: #88888f; vertical-align: top;">Correo:</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff;">{{ $contract->signer_email }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; color: #88888f; vertical-align: top;">Fecha/Hora (UTC):</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff; font-family: monospace;">{{ $contract->signed_at ? $contract->signed_at->toDateTimeString() : '' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; color: #88888f; vertical-align: top;">Dirección IP:</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff; font-family: monospace;">{{ $contract->signing_ip }}</td>
                                            </tr>
                                            @if($contract->country || $contract->city)
                                            <tr>
                                                <td style="padding: 4px 0; color: #88888f; vertical-align: top;">Ubicación:</td>
                                                <td style="padding: 4px 0 4px 10px; color: #ffffff;">
                                                    {{ collect([$contract->city, $contract->country])->filter()->implode(', ') }}
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Call to Action Button to Download PDF -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 25px; padding-top: 5px;">
                                        <a href="{{ route('contratos.download', ['token' => $contract->token]) }}" target="_blank" style="background-color: #2ecc71; color: #ffffff; text-decoration: none; padding: 14px 36px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.12em; font-size: 13px; border-radius: 30px; display: inline-block; box-shadow: 0 6px 20px rgba(46,204,113,0.3); border: 1px solid #27ae60; transition: all 0.3s ease;">
                                            Descargar Contrato Firmado (PDF) 📥
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 10px 0; font-size: 14px; line-height: 1.7; color: #aaaaaa;">
                                Hemos adjuntado a este correo electrónico una copia oficial en formato PDF del contrato firmado que contiene las cláusulas legales y los registros técnicos de auditoría. También puedes descargarlo directamente en cualquier momento pulsando el botón superior.
                            </p>
                            
                            <p style="margin: 0; font-size: 14px; line-height: 1.7; color: #aaaaaa;">
                                Por favor, guarda este archivo para tus registros personales.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #0d0d0f; border-top: 1px solid #222225; font-size: 11px; line-height: 1.8; color: #66666a; text-align: center;">
                            <p style="margin: 0 0 6px 0;">Este es un mensaje automático. Por favor no respondas a esta dirección de correo.</p>
                            <p style="margin: 0;">© {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
