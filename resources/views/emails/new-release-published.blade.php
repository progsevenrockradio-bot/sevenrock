@php
    $settings = \App\Models\ThemeSetting::current();
    $ui = $settings->uiTexts();
    $heading = $ui['email_heading_new_release_published'] ?? '¡Tu lanzamiento ha sido publicado!';
@endphp

<x-mail::message>
<div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8d8d8d;">Notificación de Publicación</div>

# {{ $heading }}

Hola,

Nos complace informarte que el nuevo lanzamiento de **{{ $newRelease->artist_name }}** titulado **"{{ $newRelease->title }}"** ya se encuentra disponible en la web de **Seven Rock Radio**.

<x-mail::panel>
@if($newRelease->cover_image_url)
<div style="text-align: center; margin-bottom: 12px;">
    <img src="{{ $newRelease->cover_image_url }}" alt="{{ $newRelease->title }}" style="max-width: 100%; max-height: 200px; border-radius: 4px; border: 1px solid #333; display: inline-block;">
</div>
@endif
<div style="font-size: 13px; color: #a1a1aa; line-height: 1.6; font-style: italic;">
"{{ \Illuminate\Support\Str::limit(strip_tags($newRelease->description), 180) }}"
</div>
</x-mail::panel>

Puedes ver la ficha del lanzamiento publicado haciendo clic en el siguiente botón:

<x-mail::button :url="$newReleaseUrl" color="primary">
Ver lanzamiento en la web
</x-mail::button>

<div style="margin-top:25px;border-top:1px solid #2c2c2c;padding-top:18px;">
    <div style="font-size:11px;color:#8d8d8d;text-transform:uppercase;letter-spacing:.12em;margin-bottom:12px;text-align:center;">Compartir en tus redes:</div>
    <div style="text-align:center;">
        <a href="https://api.whatsapp.com/send?text={{ rawurlencode('¡Hola! Mira el nuevo lanzamiento de ' . $newRelease->artist_name . ' - "' . $newRelease->title . '" en Seven Rock Radio. Escúchalo aquí: ' . $newReleaseUrl) }}" target="_blank" style="background:#25D366;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">WhatsApp</a>
        <a href="https://t.me/share/url?url={{ rawurlencode($newReleaseUrl) }}&text={{ rawurlencode('¡Hola! Mira el nuevo lanzamiento de ' . $newRelease->artist_name . ' - "' . $newRelease->title . '" en Seven Rock Radio.') }}" target="_blank" style="background:#0088cc;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">Telegram</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($newReleaseUrl) }}" target="_blank" style="background:#1877F2;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">Facebook</a>
        <a href="https://twitter.com/intent/tweet?url={{ rawurlencode($newReleaseUrl) }}&text={{ rawurlencode('¡Hola! Mira el nuevo lanzamiento de ' . $newRelease->artist_name . ' - "' . $newRelease->title . '" en @SevenRockRadio:') }}" target="_blank" style="background:#000000;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">Twitter / X</a>
    </div>
</div>

¡Muchas gracias por colaborar con nosotros!

Saludos cordiales,<br>
**El Equipo de {{ $settings->site_name }}**
</x-mail::message>
