<x-mail::message>
# OFERTA EXCLUSIVA SEVEN ROCK

@if($contactName)
¡Hola {{ $contactName }}!
@else
¡Hola!
@endif

{!! $bodyContent !!}

@if($buttonText && $buttonUrl)
<x-mail::button :url="$buttonUrl" color="primary">
{{ $buttonText }}
</x-mail::button>
@endif

---

Esta es una oferta exclusiva enviada por Seven Rock Radio.
</x-mail::message>
