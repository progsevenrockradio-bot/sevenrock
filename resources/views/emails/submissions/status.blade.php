<x-mail::message>
# Hola, {{ $submission->band_name }}

Nuestro equipo ha revisado tu maqueta **"{{ $submission->song_title }}"**.

@if($submission->status === 'approved')
Tu canción ha sido **aprobada** tras la revisión. La aprobación no implica por sí misma su publicación en la web ni su emisión en radio. Si se publica en nuestro catálogo, te escribiremos con el enlace.
@elseif($submission->status === 'rejected')
En esta ocasión tu canción no ha sido seleccionada. Te animamos a seguir enviándonos tu material en el futuro.
@endif

Gracias por compartir tu música con nosotros.

Un saludo,<br>
Equipo de Seven Rock Radio
</x-mail::message>
