<x-mail::message>
# PROMOCIÓN SEVEN ROCK

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

---

Servicios de Promoción | Seven Rock Radio
</x-mail::message>
