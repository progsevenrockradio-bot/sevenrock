<x-mail::message>
<div style="background:#0e0e10;border:1px solid #2c2c2c;padding:24px 20px;color:#e7e7e7;">
<div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8d8d8d;">{{ $campaignName }}</div>
<h1 style="margin:12px 0 0;font-size:22px;line-height:1.2;color:#f3f3f3;">{{ $subjectLine }}</h1>
<div style="margin-top:18px;font-size:14px;line-height:1.8;color:#d0d0d0;">
{!! $bodyHtml !!}
</div>
<div style="margin-top:22px;border-top:1px solid #2c2c2c;padding-top:14px;font-size:12px;line-height:1.7;color:#9c9c9c;">
<div><strong style="color:#f0f0f0;">Seven Rock Radio</strong></div>
<div>Banda: {{ $bandName }}</div>
@if ($contactPerson !== '')
<div>Contacto: {{ $contactPerson }}</div>
@endif
<div><a href="{{ $registerUrl }}" style="color:#d3a15a;text-decoration:none;">Registrarse como talento</a></div>
</div>
</div>
</x-mail::message>
