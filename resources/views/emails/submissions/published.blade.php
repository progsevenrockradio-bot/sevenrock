<x-mail::message>
# ¡Felicidades, {{ $submission->band_name }}!

Nos emociona contarte que tu tema **"{{ $submission->song_title }}"** acaba de ser incorporado de forma oficial a nuestra plataforma y ya forma parte de nuestro **Catálogo Musical (Hub Multimedia)**.

El público ya puede escucharlo, descubrir su banda y acceder a sus redes desde nuestra web principal.

<x-mail::panel>
### Tu Música Ya Está Al Aire
Asegúrate de compartir el enlace con tus seguidores para que apoyen a la banda directamente desde Seven Rock Radio.
</x-mail::panel>

<x-mail::button :url="route('new-releases.single', $release->slug)">
Escuchar en el Hub Multimedia
</x-mail::button>

Sigan haciendo buen rock,
<br>
El equipo de **{{ config('app.name') }}**
</x-mail::message>
