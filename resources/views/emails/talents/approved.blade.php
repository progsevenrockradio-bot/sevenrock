<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>¡Tu perfil ha sido aprobado! - Seven Rock Radio</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0e1215; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #dcdcdc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #0e1215; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 580px; background-color: #151b22; border: 1px solid #2b3540; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <tr>
                        <td style="background-color: #10151a; padding: 25px 30px; text-align: center; border-bottom: 2px solid #c32720;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #ffffff;">SEVEN ROCK RADIO</h1>
                            <p style="margin: 5px 0 0; font-size: 11px; letter-spacing: 3px; color: #c32720; text-transform: uppercase;">Portal de Talentos</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px;">
                            <h2 style="margin: 0 0 10px; font-size: 22px; color: #ffffff;">🎸 ¡Enhorabuena, {{ $talent->band_name }}!</h2>
                            <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6; color: #a0aab5;">
                                Tu perfil en <strong style="color: #ffffff;">Seven Rock Radio</strong> ha sido <strong style="color: #4ade80;">aprobado</strong> por nuestro equipo. Ya estás visible en el portal y tu música puede llegar a toda nuestra audiencia.
                            </p>

                            <div style="background-color: rgba(74, 222, 128, 0.08); border-left: 4px solid #4ade80; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #a0aab5;">
                                    <strong style="color: #4ade80;">Periodo activo:</strong>
                                    Tu espacio estará activo durante <strong style="color: #ffffff;">{{ \App\Models\Talent::FREE_DURATION_DAYS }} días</strong>
                                    @if($endDate)
                                        , hasta el <strong style="color: #ffffff;">{{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</strong>
                                    @endif
                                    . Después tendrás {{ $graceDays }} días adicionales de gracia para renovar antes de que el material sea eliminado.
                                </p>
                            </div>

                            <p style="margin: 20px 0 10px; font-size: 14px; color: #a0aab5;">Desde tu panel puedes:</p>
                            <ul style="margin: 0 0 25px; padding-left: 20px; font-size: 13px; color: #a0aab5; line-height: 1.8;">
                                <li>Subir y gestionar tus canciones y material multimedia</li>
                                <li>Ver tu código de referido para invitar otras bandas</li>
                                <li>Actualizar tu perfil, bio y redes sociales</li>
                                <li>Explorar opciones de suscripción para extender tu presencia</li>
                            </ul>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 30px 0 10px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $dashboardUrl }}" style="display: inline-block; background-color: #c32720; color: #ffffff; text-decoration: none; padding: 14px 32px; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; border-radius: 6px;">
                                            Ir a mi Panel de Control
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
