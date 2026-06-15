@php
    $theme = \App\Models\ThemeSetting::current();
    $year = date('Y');
    $siteName = $theme->site_name ?? config('app.name');
    $siteUrl = url('/');
    $domain = parse_url($siteUrl, PHP_URL_HOST) ?? 'www.sevenrockradio.com';
    $address = $theme->contact_address ?? '123 Rock Street, Music City, RS 12345';
    
    $facebook = $theme->social_facebook ?? null;
    $twitter = $theme->social_twitter ?? null;
    $instagram = $theme->social_instagram ?? null;
    $youtube = $theme->social_youtube ?? null;
@endphp
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto; padding: 40px 0; text-align: center; width: 570px; background-color: #161618;">
<tr>
<td class="content-cell" align="center" style="padding: 0 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #7b7b7b; font-size: 12px; text-align: center; line-height: 1.5;">

    <p style="margin: 0 0 5px 0;">Copyright &copy; {{ $year }} {{ $siteName }}.</p>
    @if($address)
    <p style="margin: 0 0 15px 0;">{{ $address }}</p>
    @endif

    <p style="margin: 0 0 25px 0;">
        <a href="{{ $siteUrl }}" style="color: #a1a1aa; text-decoration: none;">{{ $domain }}</a>
        &nbsp;|&nbsp;
        <a href="{{ route('comunidad.muro') }}" style="color: #a1a1aa; text-decoration: none;">Comunidad</a>
    </p>

    <table align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
        <tr>
            @if($facebook)
            <td align="center" style="padding: 0 6px;">
                <a href="{{ $facebook }}" target="_blank" style="display: inline-block; width: 34px; height: 34px; border-radius: 50%; background-color: #2b2b2b; color: #a1a1aa; line-height: 34px; text-align: center; font-family: sans-serif; font-weight: bold; text-decoration: none; font-size: 14px;">f</a>
            </td>
            @endif
            @if($twitter)
            <td align="center" style="padding: 0 6px;">
                <a href="{{ $twitter }}" target="_blank" style="display: inline-block; width: 34px; height: 34px; border-radius: 50%; background-color: #2b2b2b; color: #a1a1aa; line-height: 34px; text-align: center; font-family: sans-serif; font-weight: bold; text-decoration: none; font-size: 14px;">X</a>
            </td>
            @endif
            @if($instagram)
            <td align="center" style="padding: 0 6px;">
                <a href="{{ $instagram }}" target="_blank" style="display: inline-block; width: 34px; height: 34px; border-radius: 50%; background-color: #2b2b2b; color: #a1a1aa; line-height: 34px; text-align: center; font-family: sans-serif; font-weight: bold; text-decoration: none; font-size: 14px;">ig</a>
            </td>
            @endif
            @if($youtube)
            <td align="center" style="padding: 0 6px;">
                <a href="{{ $youtube }}" target="_blank" style="display: inline-block; width: 34px; height: 34px; border-radius: 50%; background-color: #2b2b2b; color: #a1a1aa; line-height: 34px; text-align: center; font-family: sans-serif; font-weight: bold; text-decoration: none; font-size: 14px;">yt</a>
            </td>
            @endif
        </tr>
    </table>

</td>
</tr>
</table>
</td>
</tr>
