<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva banda pendiente de aprobación - Seven Rock Radio</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0e1215; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #dcdcdc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #0e1215; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 620px; background-color: #14191e; border: 1px solid #222930; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    
                    {{-- Header --}}
                    <tr>
                        <td style="padding: 30px 40px; background-color: #090c0e; border-bottom: 2px solid #c32720;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; text-transform: uppercase;">
                                SEVEN ROCK <span style="color: #c32720;">RADIO</span>
                            </h1>
                            <p style="margin: 6px 0 0; font-size: 13px; color: #88929b; text-transform: uppercase; letter-spacing: 0.1em;">
                                Solicitud de Registro de Talento
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 35px 40px;">
                            <h2 style="margin: 0 0 16px; font-size: 20px; color: #ffffff;">
                                Nueva banda pendiente de revisión
                            </h2>
                            <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #a4b0be;">
                                Se ha registrado un nuevo perfil de banda en la plataforma y requiere la aprobación manual del administrador para activarse en Seven Rock Radio.
                            </p>

                            {{-- Ficha de datos --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #0a0d0f; border: 1px solid #1c2228; border-radius: 8px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 18px 22px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #7f8c8d; width: 140px;">Banda:</td>
                                                <td style="padding: 6px 0; font-size: 15px; font-weight: bold; color: #ffffff;">{{ $talent->band_name }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #7f8c8d;">Email de contacto:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #dcdcdc;">{{ $talent->email }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #7f8c8d;">Teléfono / WhatsApp:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #dcdcdc;">{{ $talent->contact_phone ?: 'No especificado' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #7f8c8d;">País:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #dcdcdc;">{{ $talent->country ?: 'No especificado' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #7f8c8d;">Plan elegido:</td>
                                                <td style="padding: 6px 0; font-size: 14px; font-weight: bold; color: #e67e22; text-transform: uppercase;">{{ $talent->plan ?: 'FREE' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #7f8c8d;">Código de referido:</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #2ecc71;">
                                                    @if($talent->referred_by_code)
                                                        <strong style="color: #2ecc71;">{{ $talent->referred_by_code }}</strong> (recibe 60 días al aprobarse)
                                                    @else
                                                        <span style="color: #88929b;">Ninguno</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Capturas de Redes Sociales --}}
                            @if($facebookScreenshotUrl || $instagramScreenshotUrl)
                                <div style="margin-bottom: 30px;">
                                    <h3 style="margin: 0 0 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.08em; color: #88929b;">
                                        Capturas de Redes Sociales Adjuntas / Enlazadas:
                                    </h3>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            @if($facebookScreenshotUrl)
                                                <td style="padding: 6px 10px 6px 0;">
                                                    <a href="{{ $facebookScreenshotUrl }}" target="_blank" style="display: inline-block; padding: 8px 16px; background-color: #1877F2; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: bold;">
                                                        Ver Captura Facebook
                                                    </a>
                                                </td>
                                            @endif
                                            @if($instagramScreenshotUrl)
                                                <td style="padding: 6px 0;">
                                                    <a href="{{ $instagramScreenshotUrl }}" target="_blank" style="display: inline-block; padding: 8px 16px; background-color: #E1306C; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: bold;">
                                                        Ver Captura Instagram
                                                    </a>
                                                </td>
                                            @endif
                                        </tr>
                                    </table>
                                </div>
                            @endif

                            {{-- Botones de Acción (Firmados, caducan en 7 días) --}}
                            <div style="margin: 35px 0 25px; text-align: center;">
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 auto;">
                                    <tr>
                                        <td style="padding: 0 10px 0 0;">
                                            <a href="{{ $approveUrl }}" 
                                               style="display: inline-block; padding: 16px 36px; background-color: #28a745; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 800; letter-spacing: 0.05em; border-radius: 8px; text-transform: uppercase; box-shadow: 0 4px 14px rgba(40,167,69,0.35);">
                                                ✓ APROBAR
                                            </a>
                                        </td>
                                        <td style="padding: 0 0 0 10px;">
                                            <a href="{{ $rejectUrl }}" 
                                               style="display: inline-block; padding: 16px 36px; background-color: #c32720; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 800; letter-spacing: 0.05em; border-radius: 8px; text-transform: uppercase; box-shadow: 0 4px 14px rgba(195,39,32,0.35);">
                                                ✕ RECHAZAR
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin: 12px 0 0; font-size: 12px; color: #62727b;">
                                    Los botones anteriores son enlaces seguros que caducan en 7 días.
                                </p>
                            </div>

                            {{-- Enlace al panel --}}
                            <div style="margin-top: 30px; text-align: center; border-top: 1px solid #1c2228; padding-top: 20px;">
                                <a href="{{ $panelUrl }}" style="font-size: 13px; color: #88929b; text-decoration: underline;">
                                    O administrar esta banda directamente desde el panel de control
                                </a>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 20px 40px; background-color: #090c0e; text-align: center; border-top: 1px solid #1c2228;">
                            <p style="margin: 0; font-size: 12px; color: #62727b;">
                                Seven Rock Radio &copy; {{ date('Y') }}. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
