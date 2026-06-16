<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Media Kit - Seven Rock Radio</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            background-color: #ffffff;
            margin: 0;
            padding: 40px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #eb3b5a;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            max-height: 100px;
            max-width: 250px;
        }
        h1 {
            color: #111111;
            font-size: 26px;
            margin-top: 20px;
            text-transform: uppercase;
        }
        h2 {
            color: #eb3b5a;
            font-size: 18px;
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 10px;
            margin-top: 30px;
            text-transform: uppercase;
        }
        .content {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
            text-align: center;
            font-size: 12px;
            color: #777777;
        }
        .social-link {
            display: inline-block;
            margin: 0 10px;
            color: #111111;
            font-weight: bold;
            text-decoration: none;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($theme['media']['logo_url']))
            <img src="{{ $theme['media']['logo_url'] }}" alt="Seven Rock Radio Logo" class="logo">
        @else
            <h1>SEVEN ROCK RADIO</h1>
        @endif
        <h1>Media Kit Oficial</h1>
    </div>

    <div class="content">
        @if($recipientName)
            <p><strong>Hola, {{ $recipientName }}</strong></p>
        @else
            <p><strong>Hola,</strong></p>
        @endif
        
        <p>Gracias por tu interés en Seven Rock Radio. A continuación, te presentamos nuestra información oficial.</p>
        
        <h2>Sobre Nosotros</h2>
        <p>
            {{ $theme['site_description'] ?? 'Seven Rock Radio es tu estación dedicada a la mejor música rock, transmitiendo 24/7 con programas en vivo, entrevistas exclusivas y la mejor selección musical.' }}
        </p>

        <h2>Estadísticas y Audiencia</h2>
        <p>Nuestra radio llega a miles de oyentes apasionados por el Rock en todo el mundo. Contamos con una comunidad muy activa y en constante crecimiento.</p>
        <ul>
            <li>Transmisión ininterrumpida 24/7.</li>
            <li>Programación enfocada en talentos emergentes y clásicos del rock.</li>
            <li>Alcance global con fuerte presencia en habla hispana.</li>
        </ul>
    </div>

    <div class="footer">
        <p><strong>Síguenos en nuestras redes:</strong></p>
        <p>
            @if(!empty($theme['social_links']))
                @foreach($theme['social_links'] as $social)
                    @if(!empty($social['url']))
                        @php
                            $net = strtolower($social['network'] ?? '');
                            $iconUrl = 'https://img.icons8.com/ios-filled/50/111111/domain.png';
                            if(str_contains($net, 'facebook')) $iconUrl = 'https://img.icons8.com/ios-filled/50/111111/facebook-new.png';
                            elseif(str_contains($net, 'instagram')) $iconUrl = 'https://img.icons8.com/ios-filled/50/111111/instagram-new.png';
                            elseif(str_contains($net, 'youtube')) $iconUrl = 'https://img.icons8.com/ios-filled/50/111111/youtube-play.png';
                            elseif(str_contains($net, 'twitter') || str_contains($net, ' x')) $iconUrl = 'https://img.icons8.com/ios-filled/50/111111/twitter.png';
                            elseif(str_contains($net, 'spotify')) $iconUrl = 'https://img.icons8.com/ios-filled/50/111111/spotify.png';
                            elseif(str_contains($net, 'tiktok')) $iconUrl = 'https://img.icons8.com/ios-filled/50/111111/tiktok.png';
                        @endphp
                        <a href="{{ $social['url'] }}" class="social-link" style="margin: 0 10px;">
                            <img src="{{ $iconUrl }}" alt="{{ $social['network'] ?? 'Social' }}" style="width: 24px; height: 24px; vertical-align: middle;">
                        </a>
                    @endif
                @endforeach
            @endif
        </p>
        <p>&copy; {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</p>
    </div>
</body>
</html>
