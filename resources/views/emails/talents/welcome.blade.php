<x-mail::message>
# ¡Bienvenido a Seven Rock Radio Talentos!

La banda **{{ $talent->band_name }}** ya forma parte del sistema de talentos.

<x-mail::panel>
**Panel:** {{ $dashboardUrl }}

**Planes:** {{ $plansUrl }}
</x-mail::panel>

Próximos pasos:

- Completa tu perfil
- Sube tu logo y media
- Activa tu suscripción si no es gratuita

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
