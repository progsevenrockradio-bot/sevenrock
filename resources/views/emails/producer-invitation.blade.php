<x-mail::message>
<div style="background:#0e0e10;border:1px solid #2c2c2c;padding:24px 20px;color:#e7e7e7;">
<div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8d8d8d;">Programa: {{ $program->program_code }}</div>
<h1 style="margin:12px 0 0;font-size:22px;line-height:1.2;color:#f3f3f3;">{{ $subjectLine }}</h1>
<div style="margin-top:18px;font-size:14px;line-height:1.8;color:#d0d0d0;">
{!! $bodyHtml !!}
</div>
</div>
</x-mail::message>
