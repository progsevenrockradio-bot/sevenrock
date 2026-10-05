<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Material eliminado - Seven Rock Radio</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0e1215; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #dcdcdc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #0e1215; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 580px; background-color: #151b22; border: 1px solid #2b3540; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <tr>
                        <td style="background-color: #10151a; padding: 25px 30px; text-align: center; border-bottom: 2px solid #5f6b78;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #ffffff;">SEVEN ROCK RADIO</h1>
                            <p style="margin: 5px 0 0; font-size: 11px; letter-spacing: 3px; color: #8b949e; text-transform: uppercase;">Portal de Talentos</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px;">
                            <h2 style="margin: 0 0 15px; font-size: 20px; color: #ffffff;">Hola, {{ $talent->band_name }}</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #a0aab5;">
                                Te informamos que han transcurrido los <strong>15 días de gracia</strong> desde el vencimiento de tu suscripción sin registrar una renovación.
                            </p>
                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #a0aab5;">
                                En cumplimiento de la cláusula de duración y destrucción segura de datos del contrato de difusión, <strong>hemos procedido a eliminar todo el material multimedia</strong> (canciones, audios, fotos y archivos) asociado a tu perfil.
                            </p>
                            <p style="margin: 0 0 25px; font-size: 13px; line-height: 1.6; color: #8b949e;">
                                La ficha de tu banda se conserva en nuestros registros en estado inactivo. Si en el futuro deseas reactivar tu presencia en la emisora, puedes ingresar con tu cuenta y elegir un plan para volver a subir tu música.
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 20px 0 10px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $plansUrl }}" style="display: inline-block; background-color: #2b3540; color: #ffffff; text-decoration: none; padding: 12px 28px; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; border-radius: 6px;">
                                            Ver planes de difusión
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #0b0f13; padding: 20px 30px; text-align: center; border-top: 1px solid #1f2730; font-size: 11px; color: #5f6b78;">
                            Seven Rock Radio &middot; La emisora del rock independiente
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
