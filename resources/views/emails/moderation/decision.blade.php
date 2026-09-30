<x-mail::message>
# Hola{{ $nombre !== '' ? ', ' . $nombre : '' }}

@if ($aprobado)
Te escribimos para contarte que tu envío ha sido **aprobado** y ya forma parte de **{{ config('app.name') }}**.
@else
Hemos revisado tu envío con atención y, en esta ocasión, **no ha sido seleccionado**. Te animamos a seguir enviándonos material: nos encanta descubrir bandas nuevas.
@endif

**{{ $etiqueta }}:** {{ $titulo }}

@if ($resumen !== '')
<x-mail::panel>
{{ $resumen }}
</x-mail::panel>
@endif

@if ($aprobado)
<x-mail::button :url="config('app.url')">
Ver la web
</x-mail::button>
@endif

Gracias por contar con nosotros y ser parte de la escena rockera.

Saludos cordiales,<br>
El equipo de **{{ config('app.name') }}**
</x-mail::message>
