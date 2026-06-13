@php
    $settings = \App\Models\ThemeSetting::current();
    $ui = $settings->uiTexts();
    $bgColor = $ui['email_background_color'] ?? '#0c0c0e';
    $titleText = $ui['email_title_verified_podcast'] ?? 'Servidor de podcast';
    $lblStreaming = $ui['email_label_streaming'] ?? 'Servidor streaming';
    $lblPodcast = $ui['email_label_podcast'] ?? 'Servidor de podcast';
    $footerText = $ui['email_footer_notification'] ?? 'Notificación de que su programa ha sido puesto en la parrilla de la radio.';
    $logoUrl = asset($settings->logo_path ?? 'assets/lucille/logo.png');

    $episodeTitle = trim((string)$episode->live_title) !== '' ? $episode->live_title : '(' . $episode->titulo_programa . ')';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=html">
    <title>{{ $titleText }}</title>
</head>
<body style="background-color: {{ $bgColor }}; color: #dcdcdc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 40px 10px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #111113; border: 1px solid #2b2b2b; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        
        <!-- Header -->
        <div style="background-color: #161619; padding: 30px; text-align: center; border-bottom: 1px solid #2b2b2b;">
            <img src="{{ $logoUrl }}" alt="{{ $settings->site_name }}" style="max-height: 50px; display: inline-block;">
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; margin: 15px 0 0 0;">
                {{ $titleText }}
            </h1>
        </div>

        <!-- Content Body -->
        <div style="padding: 30px 40px;">
            <h2 style="color: #c32720; font-size: 18px; margin-top: 0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                {{ $titleText }} verificado
            </h2>

            <p style="font-size: 14px; line-height: 1.6; color: #a9a9b2; margin-bottom: 25px;">
                El archivo de tu programa se ha subido correctamente al servidor de respaldo.
            </p>

            <!-- Panel de Detalles -->
            <div style="background-color: #18181b; border: 1px solid #2b2b2b; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 6px 0; color: #7b7b7b; width: 120px; font-weight: bold;">Programa:</td>
                        <td style="padding: 6px 0; color: #ffffff;">{{ $episode->titulo_programa }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #7b7b7b; font-weight: bold;">Episodio:</td>
                        <td style="padding: 6px 0; color: #ffffff;">#{{ $episode->numero_episodio }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #7b7b7b; font-weight: bold;">Título:</td>
                        <td style="padding: 6px 0; color: #ffffff; font-style: italic;">{{ $episodeTitle }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #7b7b7b; font-weight: bold;">Archivo:</td>
                        <td style="padding: 6px 0; color: #ffffff; font-family: monospace; font-size: 12px;">{{ basename((string) $episode->archivo_mp3) }}</td>
                    </tr>
                </table>
            </div>

            <!-- Tabla de Estados -->
            <table style="width: 100%; border-collapse: collapse; margin: 25px 0; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid #2b2b2b;">
                        <th style="padding: 10px 0; color: #ffffff; font-weight: bold; text-transform: uppercase;">Estado de Envíos</th>
                        <th style="padding: 10px 0; color: #ffffff; font-weight: bold; text-transform: uppercase;">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #222225;">
                        <td style="padding: 12px 0; color: #dcdcdc; font-weight: 600;">{{ $lblStreaming }}</td>
                        <td style="padding: 12px 0; color: #a9a9b2;">
                            {{ (string) ($episode->radioboss_status ?? '') === 'radioboss_verified' || (bool) $episode->enviado_radioboss ? 'Verificado' : 'Pendiente' }}
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #222225;">
                        <td style="padding: 12px 0; color: #dcdcdc; font-weight: 600;">{{ $lblPodcast }}</td>
                        <td style="padding: 12px 0; color: #a9a9b2;">Verificado</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #222225;">
                        <td style="padding: 12px 0; color: #dcdcdc; font-weight: 600;">Envío General</td>
                        <td style="padding: 12px 0; color: #a9a9b2;">
                            {{ $deliveryStatus === 'delivery_verified' ? 'Verificado' : 'Pendiente' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p style="font-size: 13px; line-height: 1.6; color: #7b7b7b; margin-top: 30px; border-t: 1px dashed #2b2b2b; pt: 20px; text-align: center;">
                {{ $footerText }}
            </p>
        </div>

        <!-- Footer -->
        <div style="background-color: #0e0e10; padding: 20px; text-align: center; font-size: 12px; color: #595962; border-top: 1px solid #2b2b2b;">
            Este es un correo automático enviado por {{ $settings->site_name }}.
        </div>

    </div>
</body>
</html>
