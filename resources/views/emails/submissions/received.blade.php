<x-mail::message>
# ¡Hola, {{ $submission->band_name }}!

Hemos recibido correctamente tu maqueta **"{{ $submission->song_title }}"**. 

El equipo de A&R de **Seven Rock Radio** escuchará tu propuesta muy pronto. Si encaja con nuestra programación y estilo actual, nos pondremos en contacto contigo a través de este correo electrónico para darte los siguientes pasos.

Aquí tienes un resumen de lo que nos enviaste:

<x-mail::panel>
**Banda:** {{ $submission->band_name }}<br>
**Canción:** {{ $submission->song_title }}<br>
**Redes Sociales:** {{ $submission->social_link ?? 'No especificado' }}<br>
**Estado Actual:** En revisión 🎧
</x-mail::panel>

Gracias por confiar en nuestra radio para dar a conocer tu arte. ¡Sigue rockeando!

Atentamente,<br>
**El equipo de {{ config('app.name') }}**
</x-mail::message>
