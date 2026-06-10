<x-mail::message>
<div style="background:#09090b; border:2px solid #ef4444; padding:24px 20px; color:#f4f4f5; font-family:sans-serif; border-radius:12px;">
    <div style="text-align:center; background:linear-gradient(90deg, #ef4444, #f97316); padding:15px; margin:-24px -20px 25px -20px; border-radius:10px 10px 0 0;">
        <span style="font-size:26px; font-weight:black; letter-spacing:0.15em; color:#ffffff; text-transform:uppercase; font-family:Impact, sans-serif;">SEVEN ROCK EVENTOS</span>
    </div>

    @if($contactName)
    <p style="font-size:16px; color:#ffffff; font-weight:bold; font-family:sans-serif;">¡Hola {{ $contactName }}!</p>
    @else
    <p style="font-size:16px; color:#ffffff; font-weight:bold; font-family:sans-serif;">¡Hola rockero!</p>
    @endif

    <div style="font-size:14px; line-height:1.8; color:#d4d4d8; margin:20px 0; font-family:sans-serif;">
        {!! $bodyContent !!}
    </div>

    @if($buttonText && $buttonUrl)
    <div style="text-align:center; margin:30px 0;">
        <a href="{{ $buttonUrl }}" style="background:#ef4444; color:#ffffff; text-decoration:none; padding:12px 28px; font-weight:bold; text-transform:uppercase; font-size:14px; letter-spacing:0.1em; display:inline-block; border-radius:50px; border:2px solid #ffffff; box-shadow: 0 0 15px rgba(239,68,68,0.5);">
            {{ $buttonText }}
        </a>
    </div>
    @endif

    <div style="margin-top:30px; border-top:1px solid #27272a; padding-top:15px; font-size:11px; color:#71717a; text-align:center;">
        Seven Rock Radio | Sintoniza el mejor rock y metal en directo.<br>
        <a href="https://sevenrockradio.com" style="color:#ef4444; text-decoration:none; font-weight:bold;">Sintonizar Radio</a>
    </div>
</div>
</x-mail::message>
