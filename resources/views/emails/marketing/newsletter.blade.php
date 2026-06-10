<x-mail::message>
<div style="background:#ffffff; border:1px solid #e2e8f0; padding:24px 20px; color:#334155; font-family:sans-serif; border-radius:8px;">
    <div style="text-align:center; border-bottom:2px solid #f1f5f9; padding-bottom:15px; margin-bottom:20px;">
        <span style="font-size:24px; font-weight:bold; letter-spacing:0.05em; color:#0f172a;">SEVEN ROCK NEWS</span>
        <div style="font-size:11px; text-transform:uppercase; color:#64748b; letter-spacing:0.1em; margin-top:4px;">Tu Boletín Informativo del Rock</div>
    </div>

    @if($contactName)
    <p style="font-size:15px; color:#0f172a; font-weight:bold;">Estimado/a {{ $contactName }},</p>
    @else
    <p style="font-size:15px; color:#0f172a; font-weight:bold;">Estimado/a Colaborador/a,</p>
    @endif

    <div style="font-size:14px; line-height:1.7; color:#475569; margin:15px 0 25px 0;">
        {!! $bodyContent !!}
    </div>

    @if($buttonText && $buttonUrl)
    <div style="text-align:center; margin:25px 0;">
        <a href="{{ $buttonUrl }}" style="background:#0f172a; color:#ffffff; text-decoration:none; padding:12px 24px; font-weight:bold; font-size:13px; display:inline-block; border-radius:6px;">
            {{ $buttonText }}
        </a>
    </div>
    @endif

    <div style="margin-top:30px; border-top:1px solid #e2e8f0; padding-top:15px; font-size:11px; color:#94a3b8; text-align:center;">
        Seven Rock Radio | Música y Noticias 24/7<br>
        <a href="https://sevenrockradio.com" style="color:#0f172a; text-decoration:none; font-weight:bold;">www.sevenrockradio.com</a>
    </div>
</div>
</x-mail::message>
