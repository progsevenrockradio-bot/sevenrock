<x-mail::message>
<div style="background:#1e1b4b; border:1px solid #3730a3; padding:24px 20px; color:#e0e7ff; font-family:sans-serif; border-radius:10px;">
    <div style="text-align:center; margin-bottom:20px;">
        <span style="font-size:12px; font-weight:bold; letter-spacing:0.2em; color:#818cf8; text-transform:uppercase; display:block; margin-bottom:5px;">Oportunidad Especial</span>
        <span style="font-size:26px; font-weight:bold; color:#ffffff;">OFERTA EXCLUSIVA</span>
    </div>

    @if($contactName)
    <p style="font-size:15px; color:#ffffff; font-weight:bold;">Hola {{ $contactName }},</p>
    @else
    <p style="font-size:15px; color:#ffffff; font-weight:bold;">Hola,</p>
    @endif

    <div style="background:#312e81; border:1px solid #4338ca; padding:18px; border-radius:6px; margin:20px 0; font-size:14px; line-height:1.8; color:#e0e7ff;">
        {!! $bodyContent !!}
    </div>

    @if($buttonText && $buttonUrl)
    <div style="text-align:center; margin:25px 0;">
        <a href="{{ $buttonUrl }}" style="background:#818cf8; color:#1e1b4b; text-decoration:none; padding:12px 26px; font-weight:bold; text-transform:uppercase; font-size:13px; letter-spacing:0.05em; display:inline-block; border-radius:5px; box-shadow: 0 4px 10px rgba(129,140,248,0.3);">
            {{ $buttonText }}
        </a>
    </div>
    @endif

    <div style="margin-top:30px; border-top:1px solid #312e81; padding-top:15px; font-size:11px; color:#a5b4fc; text-align:center;">
        Esta oferta es exclusiva y por tiempo limitado.<br>
        Seven Rock Radio | Promociones y Difusión Comercial
    </div>
</div>
</x-mail::message>
