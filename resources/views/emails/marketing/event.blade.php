<x-mail::message>
# SEVEN ROCK EVENTOS

@if($contactName)
¡Hola {{ $contactName }}!
@else
¡Hola rockero!
@endif

{!! $bodyContent !!}

@if($buttonText && $buttonUrl)
<x-mail::button :url="$buttonUrl" color="primary">
{{ $buttonText }}
</x-mail::button>
@endif

---

Seven Rock Radio | Sintoniza el mejor rock y metal en directo.
[Sintonizar Radio](https://sevenrockradio.com)
</x-mail::message>
