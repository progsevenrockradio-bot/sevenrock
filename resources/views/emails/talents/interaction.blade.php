<x-mail::message>
# ¡Nueva interacción en tu perfil!

Tu perfil público de **{{ $talent->band_name }}** ha recibido una nueva interacción.

<x-mail::panel>
**Tipo:** {{ ucfirst($interactionType) }}

**Origen:** IP anónima {{ $visitorIp }}

**Perfil:** {{ $profileUrl }}
</x-mail::panel>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
