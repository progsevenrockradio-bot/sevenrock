@php
    $admin = $themeAppearance['admin_texts'] ?? [];

    $tab1Rows = [
        ['site_name', 'Nombre global del sitio', 'Títulos y branding', 'Bajo'],
        ['brand_mark', 'Texto del wordmark', 'Header público', 'Bajo'],
        ['brand_mark_font', 'Fuente del wordmark', 'Header público', 'Bajo'],
        ['brand_display_mode', 'Texto o logo', 'Header público', 'Medio'],
        ['logo', 'Logo principal', 'Header y redes', 'Medio'],
        ['background', 'Fondo general', 'Layouts', 'Medio'],
        ['hero_video_file', 'Video local del hero', 'Home', 'Medio'],
        ['hero_video_url', 'Video externo del hero', 'Home', 'Alto'],
        ['hero_video_disabled', 'Apaga el video del hero', 'Home', 'Medio'],
        ['body_font', 'Fuente base', 'Texto general', 'Bajo'],
        ['heading_font', 'Fuente de títulos', 'Encabezados', 'Bajo'],
        ['accent_color', 'Color de acento', 'Botones y highlights', 'Medio'],
        ['nav_color', 'Color de navegación', 'Cabeceras y menús', 'Medio'],
        ['surface_color', 'Color de superficies', 'Cards y paneles', 'Bajo'],
        ['body_color', 'Color del texto base', 'Texto general', 'Bajo'],
        ['heading_color', 'Color de títulos', 'Encabezados', 'Bajo'],
        ['line_color', 'Color de bordes', 'Separadores', 'Bajo'],
        ['hero_slide_primary', 'Primera imagen del hero', 'Home principal', 'Bajo'],
        ['hero_slide_secondary', 'Segunda imagen del hero', 'Home principal', 'Bajo'],
        ['home_album_cover', 'Portada destacada de álbum', 'Home', 'Bajo'],
        ['home_video_image', 'Imagen destacada del video', 'Home', 'Bajo'],
    ];

    $tab2Rows = [
        ['featured_stories_json', 'Historias destacadas', 'Home', 'Alto'],
        ['latest_podcasts_json', 'Últimos podcasts', 'Home', 'Alto'],
        ['home_headings_json', 'Títulos editoriales del home', 'Home', 'Alto'],
        ['ui_texts_json', 'Textos reutilizables UI', 'Varias vistas públicas', 'Alto'],
        ['admin_texts_json', 'Textos reutilizables del panel', 'Panel admin', 'Alto'],
    ];

    $tab3Rows = [
        ['contact_form_title', 'Título del formulario de contacto', 'Contacto', 'Bajo'],
        ['contact_info_title', 'Título del bloque de info', 'Contacto', 'Bajo'],
        ['contact_description', 'Texto descriptivo', 'Contacto', 'Bajo'],
        ['contact_address', 'Dirección', 'Contacto', 'Bajo'],
        ['contact_phone_primary', 'Teléfono principal', 'Contacto y pie', 'Bajo'],
        ['contact_phone_secondary', 'Teléfono secundario', 'Contacto y pie', 'Bajo'],
        ['social_facebook', 'Facebook', 'Footer', 'Bajo'],
        ['social_instagram', 'Instagram', 'Footer', 'Bajo'],
        ['social_youtube', 'YouTube', 'Footer', 'Bajo'],
        ['social_tiktok', 'TikTok', 'Footer', 'Bajo'],
        ['social_x', 'X', 'Footer', 'Bajo'],
        ['contact_email', 'Correo público', 'Contacto y pie', 'Bajo'],
        ['notification_email', 'Correo principal de notificación', 'Sistema de emails', 'Medio'],
        ['notification_copy_email', 'Correo en copia', 'Sistema de emails', 'Medio'],
        ['notification_from_email', 'Remitente de correos', 'Sistema de emails', 'Medio'],
        ['notification_reply_to_email', 'Reply-To de correos', 'Sistema de emails', 'Medio'],
        ['notification_mailer', 'Mailer activo', 'Envío de correos', 'Alto'],
    ];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manual de ajustes - {{ $themeSettings->site_name }}</title>
    <style>
        @page { margin: 26px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f1f1f; }
        h1, h2, h3 { margin: 0; }
        .muted { color: #666; }
        .header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .hero { margin-bottom: 18px; }
        .panel { border: 1px solid #bbb; padding: 12px; margin-bottom: 12px; }
        .panel h2 { margin-bottom: 8px; font-size: 16px; }
        .summary-grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .summary-grid td { vertical-align: top; width: 33.33%; border: 1px solid #ccc; padding: 8px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th, .table td { border: 1px solid #cfcfcf; padding: 7px; text-align: left; }
        .table th { background: #efefef; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        .section { page-break-inside: avoid; margin-bottom: 14px; }
        .risk-low { color: #0f7a3b; font-weight: bold; }
        .risk-med { color: #9a6400; font-weight: bold; }
        .risk-high { color: #a11f1f; font-weight: bold; }
        .small { font-size: 10px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Seven Rock Radio - Manual de ajustes</h1>
        <div class="muted">Exportado desde el panel el {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="hero panel">
        <strong>{{ $themeSettings->site_name }}</strong>
        <div class="muted" style="margin-top: 6px;">
            Este PDF resume la configuración de <strong>/admin/settings</strong> siguiendo las tres pestañas del formulario.
        </div>
        <table class="summary-grid">
            <tr>
                <td><strong>Pestaña 1</strong><br><span class="muted">Apariencia, branding, fuentes, colores y media principal.</span></td>
                <td><strong>Pestaña 2</strong><br><span class="muted">Bloques JSON para la portada y textos reutilizables.</span></td>
                <td><strong>Pestaña 3</strong><br><span class="muted">Contacto, redes sociales y notificaciones activas.</span></td>
            </tr>
        </table>
    </div>

    <div class="section panel">
        <h2>Pestaña 1: Apariencia y Multimedia</h2>
        <table class="table">
            <thead>
                <tr><th>Campo</th><th>Qué hace</th><th>Dónde se ve</th><th>Riesgo</th></tr>
            </thead>
            <tbody>
                @foreach ($tab1Rows as [$field, $what, $where, $risk])
                    <tr>
                        <td><strong>{{ $field }}</strong></td>
                        <td>{{ $what }}</td>
                        <td>{{ $where }}</td>
                        <td class="{{ $risk === 'Alto' ? 'risk-high' : ($risk === 'Medio' ? 'risk-med' : 'risk-low') }}">{{ $risk }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section panel">
        <h2>Pestaña 2: Contenido y Textos</h2>
        <div class="small muted">Estos campos son JSON. Un error de sintaxis puede impedir el guardado o romper partes de la web.</div>
        <table class="table">
            <thead>
                <tr><th>Campo</th><th>Qué hace</th><th>Dónde se ve</th><th>Riesgo</th></tr>
            </thead>
            <tbody>
                @foreach ($tab2Rows as [$field, $what, $where, $risk])
                    <tr>
                        <td><strong>{{ $field }}</strong></td>
                        <td>{{ $what }}</td>
                        <td>{{ $where }}</td>
                        <td class="{{ $risk === 'Alto' ? 'risk-high' : 'risk-med' }}">{{ $risk }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section panel">
        <h2>Pestaña 3: Comunicaciones y Redes</h2>
        <table class="table">
            <thead>
                <tr><th>Campo</th><th>Qué hace</th><th>Dónde se ve</th><th>Riesgo</th></tr>
            </thead>
            <tbody>
                @foreach ($tab3Rows as [$field, $what, $where, $risk])
                    <tr>
                        <td><strong>{{ $field }}</strong></td>
                        <td>{{ $what }}</td>
                        <td>{{ $where }}</td>
                        <td class="{{ $risk === 'Alto' ? 'risk-high' : ($risk === 'Medio' ? 'risk-med' : 'risk-low') }}">{{ $risk }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            <strong>Estado activo de notificaciones</strong>
            <div class="small muted" style="margin-top: 4px;">
                Correo principal: {{ $themeSettings->notification_email ?: 'No definido' }}<br>
                Correo copia: {{ $themeSettings->notification_copy_email ?: 'No definido' }}<br>
                Remitente: {{ $themeSettings->notification_from_email ?: 'No definido' }}<br>
                Reply-To: {{ $themeSettings->notification_reply_to_email ?: 'No definido' }}<br>
                Mailer: {{ $themeSettings->notification_mailer ?: config('mail.default', 'log') }}
            </div>
        </div>
    </div>

    <div class="panel">
        <h2>Capturas sugeridas</h2>
        <ul>
            <li>1. Pantalla completa de ajustes con las 3 pestañas.</li>
            <li>2. Pestaña de apariencia abierta.</li>
            <li>3. Pestaña de contenido con editores JSON visibles.</li>
            <li>4. Pestaña de comunicaciones con el estado de notificaciones.</li>
        </ul>
    </div>
</body>
</html>
