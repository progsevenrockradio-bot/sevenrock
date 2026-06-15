<x-mail::message>
@if($contactName)
Hola {{ $contactName }},
@else
Hola,
@endif

{!! $bodyContent !!}

@if($buttonText && $buttonUrl)
<x-mail::button :url="$buttonUrl" color="primary">
{{ $buttonText }}
</x-mail::button>
@endif

Saludos cordiales,<br>
**El Equipo de Seven Rock Radio**<br>
Difusión, Entrevistas y Promoción de Rock
</x-mail::message>
