@php
    $settings = \App\Models\ThemeSetting::current();
    $ui = $settings->uiTexts();
    $heading = $ui['email_heading_new_release_published'] ?? '¡Tu lanzamiento ha sido publicado!';
@endphp
<x-mail::message>
<div style="background:#0e0e10;border:1px solid #2c2c2c;padding:24px 20px;color:#e7e7e7;">
<div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8d8d8d;">Notificación de Publicación</div>
<h1 style="margin:12px 0 0;font-size:22px;line-height:1.2;color:#f3f3f3;">{{ $heading }}</h1>
<div style="margin-top:18px;font-size:14px;line-height:1.8;color:#d0d0d0;">
<p>Hola,</p>
<p>Nos complace informarte que el nuevo lanzamiento de <strong>{{ $newRelease->artist_name }}</strong> titulado <strong>"{{ $newRelease->title }}"</strong> ya se encuentra disponible en la web de <strong>Seven Rock Radio</strong>.</p>

<div style="background:#18181b;border:1px solid #2c2c2c;padding:15px;margin:20px 0;border-radius:4px;text-align:left;">
    @if($newRelease->cover_image_url)
    <div style="text-align: center; margin-bottom: 12px;">
        <img src="{{ $newRelease->cover_image_url }}" alt="{{ $newRelease->title }}" style="max-width: 100%; max-height: 200px; border-radius: 4px; border: 1px solid #333; display: inline-block;">
    </div>
    @endif
    <div style="font-size: 13px; color: #a1a1aa; line-height: 1.6; font-style: italic;">
        "{{ \Illuminate\Support\Str::limit(strip_tags($newRelease->description), 180) }}"
    </div>
</div>

<p>Puedes ver la ficha del lanzamiento publicado haciendo clic en el siguiente botón:</p>
<div style="margin: 20px 0; text-align: center;">
<a href="{{ $newReleaseUrl }}" style="background:#c32720;color:#ffffff;text-decoration:none;padding:10px 20px;font-weight:bold;text-transform:uppercase;font-size:12px;letter-spacing:.12em;display:inline-block;">Ver lanzamiento en la web</a>
</div>

<div style="margin-top:25px;border-top:1px solid #2c2c2c;padding-top:18px;">
    <div style="font-size:11px;color:#8d8d8d;text-transform:uppercase;letter-spacing:.12em;margin-bottom:12px;text-align:center;">Compartir en tus redes:</div>
    <div style="text-align:center;">
        <a href="https://api.whatsapp.com/send?text={{ rawurlencode('¡Hola! Mira el nuevo lanzamiento de ' . $newRelease->artist_name . ' - "' . $newRelease->title . '" en Seven Rock Radio. Escúchalo aquí: ' . $newReleaseUrl) }}" target="_blank" style="background:#25D366;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">WhatsApp</a>
        <a href="https://t.me/share/url?url={{ rawurlencode($newReleaseUrl) }}&text={{ rawurlencode('¡Hola! Mira el nuevo lanzamiento de ' . $newRelease->artist_name . ' - "' . $newRelease->title . '" en Seven Rock Radio.') }}" target="_blank" style="background:#0088cc;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">Telegram</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($newReleaseUrl) }}" target="_blank" style="background:#1877F2;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">Facebook</a>
        <a href="https://twitter.com/intent/tweet?url={{ rawurlencode($newReleaseUrl) }}&text={{ rawurlencode('¡Hola! Mira el nuevo lanzamiento de ' . $newRelease->artist_name . ' - "' . $newRelease->title . '" en @SevenRockRadio:') }}" target="_blank" style="background:#000000;color:#ffffff;text-decoration:none;padding:6px 12px;font-size:11px;font-weight:bold;border-radius:3px;margin: 4px;display:inline-block;">Twitter / X</a>
    </div>
</div>

<p>¡Muchas gracias por colaborar con nosotros!</p>
</div>
<div style="margin-top:22px;border-top:1px solid #2c2c2c;padding-top:14px;font-size:12px;line-height:1.7;color:#9c9c9c;">
<div><strong style="color:#f0f0f0;">Seven Rock Radio</strong></div>
<div><a href="https://sevenrockradio.com" style="color:#c32720;text-decoration:none;">www.sevenrockradio.com</a></div>
</div>
</div>
</x-mail::message>
