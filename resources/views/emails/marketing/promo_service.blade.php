<x-mail::message>
<div style="background:#0e0e10; border:1px solid #2b2b2b; padding:24px 20px; color:#e7e7e7; font-family:sans-serif;">
    <div style="text-align:center; border-bottom:1px solid #2b2b2b; padding-bottom:15px; margin-bottom:20px;">
        <span style="font-size:24px; font-weight:bold; letter-spacing:0.12em; color:#c32720; text-transform:uppercase;">Seven Rock Radio</span>
    </div>

    @if($contactName)
    <p style="font-size:16px; color:#ffffff; font-weight:bold; margin-bottom:15px;">Hola {{ $contactName }},</p>
    @else
    <p style="font-size:16px; color:#ffffff; font-weight:bold; margin-bottom:15px;">Hola,</p>
    @endif

    <div style="font-size:14px; line-height:1.8; color:#cccccc; margin-bottom:25px;">
        {!! $bodyContent !!}
    </div>

    @if($buttonText && $buttonUrl)
    <div style="text-align:center; margin:30px 0;">
        <a href="{{ $buttonUrl }}" style="background:#c32720; color:#ffffff; text-decoration:none; padding:12px 28px; font-weight:bold; text-transform:uppercase; font-size:13px; letter-spacing:0.12em; display:inline-block; border-radius:4px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
            {{ $buttonText }}
        </a>
    </div>
    @endif

    <div style="margin-top:30px; border-top:1px solid #2b2b2b; padding-top:15px; font-size:11px; color:#888888; text-align:center;">
        Estás recibiendo este correo porque tu banda o agencia ha estado en contacto con Seven Rock Radio.<br>
        Si no deseas recibir más comunicaciones, puedes responder a este correo solicitando tu baja.
    </div>
</div>
</x-mail::message>
