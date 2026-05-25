<x-mail::message>
# Tu suscripción está por vencer

El plan actual de **{{ $talent?->band_name ?? 'tu talento' }}** vence pronto.

<x-mail::panel>
**Plan:** {{ ucfirst((string) $subscription->plan) }}

**Vence:** {{ $subscription->end_date?->format('d/m/Y') ?? 'N/D' }}
</x-mail::panel>

<x-mail::button :url="$renewUrl">
Renovar ahora
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
