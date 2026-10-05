<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu perfil está oculto - Seven Rock Radio</title>
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
                            <h2 style="margin: 0 0 15px; font-size: 20px; color: #ffffff;">Hola, {{ $talent->band_name }}</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #a0aab5;">
                                Tu periodo de difusión ha vencido y tu perfil ha pasado a estar <strong>OCULTO</strong> en el portal de Seven Rock Radio. Ningún visitante público podrá ver tu ficha ni escuchar tus canciones mientras permanezca oculto.
                            </p>
                            <div style="background-color: rgba(195, 39, 32, 0.1); border-left: 4px solid #c32720; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #ffb4b0;">
                                    <strong>Periodo de gracia:</strong> Tienes <strong>{{ $graceDays }} días</strong> a partir de hoy para renovar tu suscripción. Si no renuevas dentro de este plazo, todo el material multimedia (canciones, fotos y documentos) será <strong>eliminado definitivamente</strong> de nuestros servidores, tal como establece el contrato de difusión.
                                </p>
                            </div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 30px 0 10px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $renewalUrl }}" style="display: inline-block; background-color: #c32720; color: #ffffff; text-decoration: none; padding: 14px 32px; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; border-radius: 6px;">
                                            Renovar mi perfil ahora
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
