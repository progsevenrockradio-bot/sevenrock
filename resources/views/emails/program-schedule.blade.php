<x-mail::message>
# Horarios de Programas

@if(!empty($customMessage))
{!! nl2br(e($customMessage)) !!}

---
@endif

A continuación se detalla la programación seleccionada:

@foreach($groupedPrograms as $group)
## {{ $group['day'] }}

<x-mail::table>
| Hora | Programa | Conductor | Duración |
|:-----|:---------|:----------|:---------|
@foreach($group['programs'] as $program)
| **{{ $program['time'] }}** | {{ $program['name'] }} | {{ $program['conductor'] }} | {{ $program['duration'] }} |
@endforeach
</x-mail::table>

@endforeach

Adjunto a este correo encontrarás la versión en PDF con más detalles.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
