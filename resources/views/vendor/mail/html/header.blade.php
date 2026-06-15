@props(['url'])
<tr>
<td class="header">
@php
    $logoUrl = \App\Models\ThemeSetting::current()->logo_url ?? null;
@endphp
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoUrl)
    <img src="{{ url($logoUrl) }}" class="logo" alt="{{ config('app.name') }}" style="max-height: 75px; width: auto; object-fit: contain;">
@else
    {!! $slot !!}
@endif
</a>
</td>
</tr>
