<x-mail::message>
<div style="background:#0e0e10;border:1px solid #2c2c2c;padding:24px 20px;color:#e7e7e7;">
<h1 style="margin:0 0 16px 0;font-size:20px;line-height:1.3;color:#f3f3f3;border-bottom:1px solid #2c2c2c;padding-bottom:12px;">{{ $subjectLine }}</h1>
<div style="margin-top:18px;font-size:14px;line-height:1.8;color:#d0d0d0;white-space:pre-wrap;">
{!! $bodyHtml !!}
</div>
<div style="margin-top:28px;border-top:1px solid #2c2c2c;padding-top:16px;font-size:12px;line-height:1.7;color:#9c9c9c;">
<div><strong style="color:#c32720;">Seven Rock Radio</strong></div>
<div><a href="{{ $websiteUrl }}" style="color:#7b7b7b;text-decoration:none;">SevenRockRadio.com</a></div>
</div>
</div>
</x-mail::message>
