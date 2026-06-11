@php
    $theme = \App\Models\ThemeSetting::current();
    $siteUrl = config('app.url', 'https://sevenrockradio.com');
    $logoUrl = $theme->logo_url ?? $siteUrl . '/assets/lucille/logo.png';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Únete a Seven Rock Radio Talentos</title>
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
                            @if(!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="Seven Rock Radio" style="max-height: 55px; width: auto; display: block;" />
                            @else
                                <span style="font-size: 22px; font-weight: bold; letter-spacing: 0.15em; text-transform: uppercase; color: #ffffff;">Seven Rock Radio</span>
                            @endif
                            <div style="font-size: 10px; color: #88888f; text-transform: uppercase; letter-spacing: 0.25em; margin-top: 12px; font-weight: 600;">Registro de Bandas & Artistas</div>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 20px 0; font-size: 22px; font-weight: 700; line-height: 1.3; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em; border-left: 3px solid #c32720; padding-left: 14px;">
                                ¡Queremos escuchar tu música!
                            </h1>
                            
                            <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.7; color: #cccccc;">
                                Hola, <strong style="color: #ffffff;">{{ $senderName }}</strong>:
                            </p>
                            
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.7; color: #cccccc;">
                                Nos alegra tu interés en formar parte de Seven Rock Radio con tu proyecto musical: <strong style="color: #ffffff;">{{ $bandName }}</strong>.
                            </p>
                            
                            <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.7; color: #cccccc;">
                                Para publicar tus canciones en el **Muro del Rock**, contar con tu perfil oficial y que toda nuestra comunidad escuche tu propuesta, puedes registrarte en el plan que mejor se adapte a tus necesidades:
                            </p>

                            <!-- Plan: FREE -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #1a1a1e; border: 1px solid #2d2d33; border-radius: 8px; margin-bottom: 16px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="vertical-align: top;">
                                                    <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #88888f; display: block; margin-bottom: 2px;">Plan Básico Gratuito</span>
                                                    <strong style="font-size: 20px; color: #ffffff;">FREE</strong>
                                                </td>
                                                <td align="right" style="vertical-align: middle;">
                                                    <span style="font-size: 20px; font-weight: bold; color: #c32720;">0€</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding-top: 12px; font-size: 14px; color: #bbbbbb; border-top: 1px solid #28282b; margin-top: 12px;">
                                                    <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                                                        <li>Perfil básico de banda/artista.</li>
                                                        <li>Subida de 1 foto de perfil.</li>
                                                        <li>Subida de 1 canción (MP3).</li>
                                                    </ul>
                                                    <div style="margin-top: 15px; text-align: right;">
                                                        <a href="{{ route('talents.register', ['plan' => 'free']) }}" target="_blank" style="background-color: #222225; color: #ffffff; text-decoration: none; padding: 8px 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; font-size: 11px; border-radius: 4px; display: inline-block; border: 1px solid #333336;">
                                                            Registrar Plan Free
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Plan: BASIC -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #1a1a1e; border: 1px solid #2d2d33; border-radius: 8px; margin-bottom: 16px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="vertical-align: top;">
                                                    <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #88888f; display: block; margin-bottom: 2px;">Plan Recomendado Inicial</span>
                                                    <strong style="font-size: 20px; color: #ffffff;">BASIC</strong>
                                                </td>
                                                <td align="right" style="vertical-align: middle;">
                                                    <span style="font-size: 20px; font-weight: bold; color: #c32720;">3.5€<span style="font-size: 12px; color: #88888f; font-weight: normal;">/mes</span></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding-top: 12px; font-size: 14px; color: #bbbbbb; border-top: 1px solid #28282b; margin-top: 12px;">
                                                    <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                                                        <li>Subida de hasta 5 canciones.</li>
                                                        <li>Subida de hasta 10 fotos.</li>
                                                        <li>Acceso a estadísticas básicas de reproducción.</li>
                                                    </ul>
                                                    <div style="margin-top: 15px; text-align: right;">
                                                        <a href="{{ route('talents.register', ['plan' => 'basic']) }}" target="_blank" style="background-color: #222225; color: #ffffff; text-decoration: none; padding: 8px 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; font-size: 11px; border-radius: 4px; display: inline-block; border: 1px solid #333336;">
                                                            Registrar Plan Basic
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Plan: PRO -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #1a1a1e; border: 1px solid #2d2d33; border-radius: 8px; margin-bottom: 16px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="vertical-align: top;">
                                                    <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #88888f; display: block; margin-bottom: 2px;">Plan Profesional</span>
                                                    <strong style="font-size: 20px; color: #ffffff;">PRO</strong>
                                                </td>
                                                <td align="right" style="vertical-align: middle;">
                                                    <span style="font-size: 20px; font-weight: bold; color: #c32720;">7€<span style="font-size: 12px; color: #88888f; font-weight: normal;">/mes</span></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding-top: 12px; font-size: 14px; color: #bbbbbb; border-top: 1px solid #28282b; margin-top: 12px;">
                                                    <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                                                        <li>Subida de hasta 20 canciones.</li>
                                                        <li>Subida de hasta 50 fotos.</li>
                                                        <li>Sección de documentos de prensa (dossier, prensa, etc.).</li>
                                                        <li>Integración de videos oficiales y estadísticas detalladas.</li>
                                                    </ul>
                                                    <div style="margin-top: 15px; text-align: right;">
                                                        <a href="{{ route('talents.register', ['plan' => 'pro']) }}" target="_blank" style="background-color: #222225; color: #ffffff; text-decoration: none; padding: 8px 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; font-size: 11px; border-radius: 4px; display: inline-block; border: 1px solid #333336;">
                                                            Registrar Plan Pro
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Plan: PREMIUM -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #1a1a1e; border: 1px solid #2d2d33; border-radius: 8px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="vertical-align: top;">
                                                    <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #88888f; display: block; margin-bottom: 2px;">Plan Máxima Visibilidad</span>
                                                    <strong style="font-size: 20px; color: #ffffff;">PREMIUM</strong>
                                                </td>
                                                <td align="right" style="vertical-align: middle;">
                                                    <span style="font-size: 20px; font-weight: bold; color: #c32720;">11€<span style="font-size: 12px; color: #88888f; font-weight: normal;">/mes</span></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding-top: 12px; font-size: 14px; color: #bbbbbb; border-top: 1px solid #28282b; margin-top: 12px;">
                                                    <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                                                        <li>Capacidad ampliada (50 canciones, fotos y documentos ilimitados).</li>
                                                        <li>Historia destacada automática en el portal de talentos.</li>
                                                        <li>Prioridad de aparición y promoción en la página de inicio pública.</li>
                                                    </ul>
                                                    <div style="margin-top: 15px; text-align: right;">
                                                        <a href="{{ route('talents.register', ['plan' => 'premium']) }}" target="_blank" style="background-color: #222225; color: #ffffff; text-decoration: none; padding: 8px 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; font-size: 11px; border-radius: 4px; display: inline-block; border: 1px solid #333336;">
                                                            Registrar Plan Premium
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Main CTA -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 30px;">
                                        <a href="{{ route('talents.register') }}" target="_blank" style="background-color: #c32720; color: #ffffff; text-decoration: none; padding: 14px 36px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.12em; font-size: 13px; border-radius: 30px; display: inline-block; box-shadow: 0 6px 20px rgba(195,39,32,0.4); border: 1px solid #d42c24; transition: all 0.3s ease;">
                                            Ver Todos los Planes y Registrarse 🚀
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #cccccc; border-top: 1px solid #222225; padding-top: 20px; text-align: center;">
                                Una vez registrado, podrás firmar de manera digital el acuerdo de colaboración y tu música estará sonando para todos nuestros oyentes a nivel global.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #0d0d0f; border-top: 1px solid #222225; font-size: 11px; line-height: 1.8; color: #66666a; text-align: center;">
                            <p style="margin: 0 0 6px 0;">Has recibido este correo informativo porque solicitaste pertenecer a la radio en nuestro formulario de contacto.</p>
                            <p style="margin: 0;">© {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
