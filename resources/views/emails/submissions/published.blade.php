<x-mail::message>
# Hola, {{ $submission->band_name }}

Tu canción **"{{ $submission->song_title }}"** ya está publicada en el catálogo web de **Seven Rock Radio** (Hub Multimedia). Cualquier persona con el enlace puede escucharla en nuestra web.

<x-mail::button :url="$release->url">
Ver tu canción en el Hub
</x-mail::button>

Esta publicación es en la web. No supone que la canción vaya a sonar en nuestra emisión de radio.

Gracias por enviarnos tu música.

Un saludo,<br>
Equipo de Seven Rock Radio
</x-mail::message>
