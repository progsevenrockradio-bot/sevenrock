<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>¡Has ganado una recompensa en Seven Rock Radio!</title>
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
                            <h2 style="margin: 0 0 10px; font-size: 22px; color: #ffffff;">🏆 ¡Premio desbloqueado, {{ $referrer->band_name }}!</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #a0aab5;">
                                Gracias a tu trabajo de boca a boca, ya tienes <strong style="color: #ffffff;">{{ $activeReferrals }} banda(s)</strong> activas que se unieron a Seven Rock Radio gracias a ti. Has desbloqueado:
                            </p>

                            <div style="background-color: rgba(195, 39, 32, 0.12); border: 1px solid rgba(195, 39, 32, 0.4); padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center;">
                                <p style="margin: 0 0 8px; font-size: 13px; color: #a0aab5; text-transform: uppercase; letter-spacing: 1px;">Tu recompensa</p>
                                <p style="margin: 0 0 6px; font-size: 22px; font-weight: 800; color: #ffffff;">{{ $rewardLabel }}</p>
                                <p style="margin: 0; font-size: 13px; color: #4ade80;">+{{ $bonusDays }} días adicionales añadidos a tu suscripción</p>
                            </div>

                            <p style="margin: 20px 0; font-size: 13px; line-height: 1.6; color: #a0aab5;">
                                Los días adicionales han sido añadidos automáticamente a tu suscripción activa. Sigue compartiendo tu código de referido para desbloquear más recompensas con el siguiente hito.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 25px 0 10px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $dashboardUrl }}" style="display: inline-block; background-color: #c32720; color: #ffffff; text-decoration: none; padding: 14px 32px; font-size: 13px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; border-radius: 6px;">
                                            Ver mi Panel y mi Código
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
