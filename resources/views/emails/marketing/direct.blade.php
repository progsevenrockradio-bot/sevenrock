<x-mail::message>
<div style="background:#ffffff; padding:10px 5px; color:#1a1a1a; font-family:Georgia, serif; font-size:16px; line-height:1.8;">
    @if($contactName)
    <p>Hola {{ $contactName }},</p>
    @else
    <p>Hola,</p>
    @endif

    <div style="margin:20px 0;">
        {!! $bodyContent !!}
    </div>

    @if($buttonText && $buttonUrl)
    <div style="margin:30px 0; text-align:left;">
        <a href="{{ $buttonUrl }}" style="background:#1a1a1a; color:#ffffff; text-decoration:none; padding:10px 20px; font-weight:bold; font-size:14px; display:inline-block; border-radius:3px; font-family:sans-serif;">
            {{ $buttonText }}
        </a>
    </div>
    @endif

    <p style="margin-top:40px; border-top:1px solid #eaeaea; padding-top:15px; font-size:14px; color:#555555; line-height:1.5;">
        Saludos cordiales,<br><br>
        <strong>El Equipo de Seven Rock Radio</strong><br>
        <span style="font-size:12px; color:#777777;">Difusión, Entrevistas y Promoción de Rock</span><br>
        <a href="https://sevenrockradio.com" style="color:#c32720; text-decoration:none; font-size:12px;">sevenrockradio.com</a>
    </p>
</div>
</x-mail::message>
