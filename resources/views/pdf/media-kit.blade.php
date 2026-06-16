<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Media Kit - Seven Rock Radio</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #222222;
            background-color: #ffffff;
            margin: 0;
            padding: 40px;
            position: relative;
        }
        .watermark {
            position: fixed;
            top: 200px;
            left: 0;
            right: 0;
            text-align: center;
            opacity: 0.08;
            z-index: -1000;
        }
        .watermark img {
            max-width: 500px;
            max-height: 500px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #eb3b5a;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            max-height: 120px;
            max-width: 300px;
        }
        h1 {
            color: #111111;
            font-size: 26px;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        h2 {
            color: #eb3b5a;
            font-size: 18px;
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 8px;
            margin-top: 30px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .content {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        .content p {
            margin-bottom: 15px;
            text-align: justify;
        }
        .content ul {
            margin-top: 5px;
            margin-bottom: 20px;
            padding-left: 20px;
        }
        .content li {
            margin-bottom: 8px;
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
            text-decoration: none;
        }
        .highlight {
            font-weight: bold;
            color: #111111;
        }
    </style>
</head>
<body>
    @php
        $logoUrl = !empty($theme['media']['logo_url']) ? $theme['media']['logo_url'] : asset('assets/lucille/logo.png');
    @endphp

    @if($includeLogo ?? true)
    <div class="watermark">
        <img src="{{ $logoUrl }}" alt="">
    </div>
    @endif

    <div class="header">
        @if($includeLogo ?? true)
            <img src="{{ $logoUrl }}" alt="Seven Rock Radio Logo" class="logo">
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
        
        <h2>Acerca de Nosotros</h2>
        <p>
            ¡Bienvenidos a <span class="highlight">Seven Rock Radio</span>, la señal definitiva y el santuario digital para los verdaderos amantes del rock!
        </p>
        <p>
            Somos una comunidad impulsada por la frecuencia, los decibelios y el poderoso mundo del rock. Desde los clásicos inmortales que marcaron la historia hasta las nuevas promesas que mantienen la llama encendida, nuestra misión es transmitir y celebrar la música que nos hace vibrar.
        </p>

        <h2>Nuestra Historia</h2>
        <p>
            Nacimos de un grupo de amigos con una colección invaluable de vinilos y un propósito claro: crear un espacio donde el rock sea el protagonista absoluto. Lo que comenzó como un sueño entre riffs y guitarras distorsionadas, hoy ha crecido hasta convertirse en tu estación de radio online de confianza, llevando la mejor energía rockera directamente a tus oídos, estés donde estés.
        </p>

        <h2>Lo Que Ofrecemos en Nuestra Sintonía</h2>
        <ul>
            <li><span class="highlight">Transmisión 24/7:</span> El mejor catálogo musical de rock sin interrupciones, sonando todo el día, todos los días.</li>
            <li><span class="highlight">Programación en Vivo:</span> Shows exclusivos conducidos por apasionados del género, con análisis de álbumes, historia musical y pura actitud.</li>
            <li><span class="highlight">Todas las Épocas:</span> Una rotación musical curada por expertos que viaja desde las leyendas del rock clásico hasta el metal, indie y las bandas emergentes.</li>
            <li><span class="highlight">Actualidad y Novedades:</span> Te mantenemos al tanto de los últimos lanzamientos, giras y noticias de tus artistas favoritos durante nuestras transmisiones.</li>
        </ul>

        <h2>Nuestra Filosofía</h2>
        <p>
            Creemos que el rock no es solo un género musical; es una actitud, una forma de expresión y un estilo de vida. Es la pasión que nos une y nos impulsa a subir el volumen. En <span class="highlight">sevenrockradio.com</span>, cada acorde cuenta una historia y cada bloque musical es una aventura.
        </p>

        <h2>Únete a la Frecuencia</h2>
        <p>
            Sintoniza, descubre y sumérgete en el ritmo que nunca muere. Porque aquí el rock no solo se escucha, se vive.
        </p>
        <p class="highlight" style="font-size: 16px; margin-top: 20px;">
            ¡Rock on! 🤘
        </p>
    </div>

    <div class="footer">
        <p><strong>Síguenos en nuestras redes:</strong></p>
        <div style="margin-top: 15px; margin-bottom: 15px;">
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
                        <a href="{{ $social['url'] }}" class="social-link">
                            <img src="{{ $iconUrl }}" alt="{{ $social['network'] ?? 'Social' }}" width="24" height="24">
                        </a>
                    @endif
                @endforeach
            @endif
        </div>
        <p>&copy; {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</p>
    </div>
</body>
</html>
