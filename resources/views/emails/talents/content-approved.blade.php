<x-mail::message>
# Contenido moderado

Se ha eliminado un archivo de tu perfil de talentos:

<x-mail::panel>
**Archivo:** {{ $media->title ?: $media->filename }}

**Tipo:** {{ ucfirst($media->type) }}
</x-mail::panel>

Si crees que esto fue un error, revisa el contenido desde tu panel.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
