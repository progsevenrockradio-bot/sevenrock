@props(['url'])
<tr>
<td class="header">
@php
    $logoUrl = \App\Models\ThemeSetting::current()->logo_url ?? null;
@endphp
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if ($logoUrl)
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
        <tr>
            <td style="vertical-align: middle; padding-right: 15px;">
                <img src="{{ url($logoUrl) }}" class="logo" alt="{{ config('app.name') }}" style="max-height: 55px; width: auto; object-fit: contain; vertical-align: middle; display: block;">
            </td>
            <td style="vertical-align: middle;">
                <span style="font-family: 'Rock Salt', cursive, sans-serif; color: #c32720; font-size: 20px; font-weight: normal; text-transform: none; letter-spacing: normal; text-shadow: 0 1.5px 3px rgba(0,0,0,0.3); display: inline-block; padding-top: 5px;">{{ \App\Models\ThemeSetting::current()->site_name ?? 'Seven Rock Radio' }}</span>
            </td>
        </tr>
    </table>
@else
    {!! $slot !!}
@endif
</a>
</td>
</tr>
