<x-mail::message>
# Tu suscripción ha expirado

La suscripción de **{{ $talent?->band_name ?? 'tu talento' }}** ha expirado.

<x-mail::panel>
**Plan:** {{ ucfirst((string) $subscription->plan) }}

**Fecha de vencimiento:** {{ $subscription->end_date?->format('d/m/Y') ?? 'N/D' }}
</x-mail::panel>

Con el plan vencido dejarás de aparecer en listados y la subida de contenido quedará bloqueada hasta reactivar la suscripción.

<x-mail::button :url="$renewUrl">
Reactivar suscripción
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
