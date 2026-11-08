<x-mail::message>
<div style="font-size: 11px; color: #c32720; text-transform: uppercase; letter-spacing: 0.18em; font-weight: 700; margin-bottom: 6px;">
Comunidad de Bandas · Muro del Rock
</div>

# Hola, {{ $bandName }}

Hace unos días publicamos tu tema **"{{ $songTitle }}"** en el catálogo web de Seven Rock Radio (<a href="{{ $releaseUrl }}" style="color: #c32720; text-decoration: underline;">puedes escucharlo aquí</a>). Gracias de nuevo por compartir tu trabajo con nosotros.

Como al enviarnos tu maqueta nos indicaste que querías recibir información sobre los servicios del **Muro del Rock**, te escribimos para presentarte esta opción de forma breve y clara.

<x-mail::panel>
<div style="border-left: 3px solid #c32720; padding-left: 12px;">
<div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #dcdcdc; margin-bottom: 4px;">
Tu espacio propio en el Muro del Rock
</div>
<div style="font-size: 15px; color: #ffffff; font-weight: 600; margin-bottom: 8px;">
Plan Básico · <span style="color: #c32720;">{{ $basicPriceLabel }}</span>
</div>
<div style="font-size: 14px; color: #a1a1aa; line-height: 1.55;">
El Muro del Rock es el directorio de bandas de Seven Rock Radio donde cada proyecto cuenta con su propia ficha pública en nuestra web para reunir su perfil, su biografía, su música y sus enlaces oficiales en un solo lugar.
</div>
</div>
</x-mail::panel>

<x-mail::button :url="$ctaUrl">
Conocer el plan Básico ({{ $basicPriceLabel }})
</x-mail::button>

Contratar este servicio es opcional y no condiciona la publicación de tu canción en nuestro catálogo. La publicación está sujeta a nuestros criterios editoriales y es independiente de la programación de radio.

Si tienes cualquier consulta antes de decidir, puedes responder directamente a este correo y te atenderemos personalmente.

Un saludo,<br>
**{{ $signature }}**

<x-slot:subcopy>
<div style="font-size: 12px; color: #8b8b93; line-height: 1.5;">
Recibes este mensaje porque marcaste la casilla opcional de información sobre el Muro del Rock al enviar tu maqueta a Seven Rock Radio.<br>
Si prefieres no recibir más comunicaciones sobre nuestros planes y servicios, puedes <a href="{{ $unsubscribeUrl }}" style="color: #dcdcdc; text-decoration: underline;">darte de baja con un clic aquí</a> (sin necesidad de iniciar sesión).
</div>
</x-slot:subcopy>
</x-mail::message>

