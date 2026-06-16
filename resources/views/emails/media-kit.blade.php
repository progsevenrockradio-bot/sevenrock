<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Kit - Seven Rock Radio</title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #050505; color: #ffffff; margin: 0; padding: 20px; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #121212; border: 1px solid #222222; border-radius: 8px; overflow: hidden;">
        
        <!-- Header -->
        <div style="text-align: center; padding: 40px 20px 30px; background: linear-gradient(to bottom, #1a1a1a, #121212); border-bottom: 2px solid #eb3b5a;">
            <img src="{{ $theme['media']['logo_url'] ?? asset('assets/lucille/logo.png') }}" alt="Seven Rock Radio Logo" style="max-width: 200px; height: auto;">
        </div>

        <!-- Content -->
        <div style="padding: 40px 30px; line-height: 1.6; color: #e0e0e0;">
            <h1 style="font-size: 20px; font-weight: bold; margin-top: 0; margin-bottom: 25px; color: #ffffff; text-align: center; text-transform: uppercase; letter-spacing: 1px;">
                @if($recipientName)
                    Hola, {{ $recipientName }}
                @else
                    Hola,
                @endif
            </h1>

            @if($customMessage)
                <div style="background-color: #1a1a1a; padding: 20px; border-left: 4px solid #eb3b5a; margin-bottom: 30px; border-radius: 0 4px 4px 0; font-size: 15px; color: #f0f0f0;">
                    {!! nl2br(e($customMessage)) !!}
                </div>
            @endif

            <p style="text-align: center; font-size: 16px;">Nos complace compartir contigo nuestra información oficial y Media Kit.</p>

            <div style="background-color: #1a1a1a; padding: 25px; border-radius: 8px; margin-top: 35px; border: 1px solid #222222;">
                <h2 style="font-size: 14px; font-weight: bold; margin-top: 0; margin-bottom: 15px; color: #eb3b5a; text-transform: uppercase; letter-spacing: 1px;">Sobre Nosotros</h2>
                <p style="margin-bottom: 0; font-size: 14px;">
                    {{ $theme['site_description'] ?? 'Seven Rock Radio es tu estación dedicada a la mejor música rock, transmitiendo 24/7 con programas en vivo, entrevistas exclusivas y la mejor selección musical.' }}
                </p>
                <div style="text-align: center; margin-top: 25px;">
                    <a href="{{ url('/') }}" style="display: inline-block; background-color: #eb3b5a; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 4px; font-weight: bold; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Visitar Sitio Web</a>
                </div>
            </div>
        </div>

        <!-- Footer / Redes Sociales -->
        <div style="text-align: center; padding: 35px 20px; background-color: #0a0a0a; border-top: 1px solid #1a1a1a;">
            
            @if(!empty($theme['social_links']))
                <div style="margin-bottom: 30px;">
                    <div style="font-size: 12px; font-weight: bold; margin-bottom: 15px; color: #777777; text-transform: uppercase; letter-spacing: 1.5px;">Síguenos en nuestras redes</div>
                    @foreach($theme['social_links'] as $social)
                        @if(!empty($social['url']))
                            <a href="{{ $social['url'] }}" style="display: inline-block; margin: 5px; color: #ffffff; text-decoration: none; background-color: #2a2a2a; padding: 8px 16px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                {{ $social['network'] ?? 'Social' }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            <div style="font-size: 12px; color: #666666; margin-top: 20px;">
                <p style="color: #999999; margin-bottom: 15px;">📎 El Media Kit oficial ha sido adjuntado a este correo en formato PDF.</p>
                <p style="margin-bottom: 0;">&copy; {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados.</p>
            </div>
        </div>

    </div>
</body>
</html>
