<x-mail::message>
<div style="background:#0e0e10;border:1px solid #2c2c2c;padding:24px 20px;color:#e7e7e7;">
<div style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8d8d8d;">Notificación de Publicación</div>
<h1 style="margin:12px 0 0;font-size:22px;line-height:1.2;color:#f3f3f3;">¡Tu contenido ha sido publicado!</h1>
<div style="margin-top:18px;font-size:14px;line-height:1.8;color:#d0d0d0;">
<p>Hola,</p>
<p>Nos complace informarte que el contenido que nos enviaste con el título <strong>"{{ $post->title }}"</strong> ya se encuentra disponible en la web de <strong>Seven Rock Radio</strong>.</p>
<p>Puedes leer la entrada publicada haciendo clic en el siguiente botón:</p>
<div style="margin: 20px 0; text-align: center;">
<a href="{{ $postUrl }}" style="background:#c32720;color:#ffffff;text-decoration:none;padding:10px 20px;font-weight:bold;text-transform:uppercase;font-size:12px;letter-spacing:.12em;display:inline-block;">Ver entrada en la web</a>
</div>
<p>¡Muchas gracias por colaborar con nosotros!</p>
</div>
<div style="margin-top:22px;border-top:1px solid #2c2c2c;padding-top:14px;font-size:12px;line-height:1.7;color:#9c9c9c;">
<div><strong style="color:#f0f0f0;">Seven Rock Radio</strong></div>
<div><a href="https://sevenrockradio.com" style="color:#c32720;text-decoration:none;">www.sevenrockradio.com</a></div>
</div>
</div>
</x-mail::message>
