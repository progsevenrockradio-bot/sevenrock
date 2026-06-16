<x-mail::message>
# Hola, {{ $submission->band_name }}

Queremos informarte que tu maqueta **"{{ $submission->song_title }}"** ha sido revisada por nuestro equipo de curación.

@if($submission->status === 'approved')
¡Felicidades! 🎉 Tu canción ha sido **aprobada** y pronto formará parte de nuestra programación. Mantente en sintonía.
@elseif($submission->status === 'rejected')
Lamentablemente, en esta ocasión tu canción no ha sido seleccionada para nuestra programación. Te animamos a seguir enviando tu material en el futuro.
@endif

Gracias por compartir tu música con nosotros y ser parte de la escena rockera.

Saludos cordiales,<br>
El equipo de {{ config('app.name') }}
</x-mail::message>
