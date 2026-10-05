<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu espacio en Seven Rock Radio vence pronto</title>
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
                            <h2 style="margin: 0 0 15px; font-size: 20px; color: #ffffff;">⏰ Hola, {{ $talent->band_name }}</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #a0aab5;">
                                Te avisamos que tu espacio de difusión en Seven Rock Radio
                                <strong style="color: #facc15;">vence en {{ $daysLeft }} {{ $daysLeft === 1 ? 'día' : 'días' }}</strong>.
                                Cuando expire, tu perfil pasará a <strong>OCULTO</strong> durante un periodo de gracia de {{ $graceDays }} días,
                                tras el cual todo tu material será <strong>eliminado definitivamente</strong>.
                            </p>

                            <div style="background-color: rgba(250, 204, 21, 0.08); border-left: 4px solid #facc15; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #a0aab5;">
                                    <strong style="color: #facc15;">¿Qué puedes hacer ahora?</strong><br>
                                    Renueva o actualiza tu plan antes de que venza y mantén tu música sonando en Seven Rock Radio sin interrupciones. Tenemos planes económicos desde 0€.
                                </p>
                            </div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 30px 0 10px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $renewalUrl }}" style="display: inline-block; background-color: #c32720; color: #ffffff; text-decoration: none; padding: 14px 32px; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; border-radius: 6px;">
                                            Ver planes y renovar ahora
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 25px 0 0; font-size: 12px; line-height: 1.5; color: #5f6b78; text-align: center;">
                                Si ya no deseas continuar, no es necesario que hagas nada. Tu perfil simplemente pasará a oculto al vencer el plazo.<br>
                                <a href="{{ $dashboardUrl }}" style="color: #c32720; text-decoration: none;">Accede a tu panel →</a>
                            </p>
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
