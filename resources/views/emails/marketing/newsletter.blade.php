<x-mail::message>
# SEVEN ROCK NEWS
**Tu Boletín Informativo del Rock**

@if($contactName)
Estimado/a {{ $contactName }},
@else
Estimado/a Amante del Rock,
@endif

{!! $bodyContent !!}

@if($buttonText && $buttonUrl)
<x-mail::button :url="$buttonUrl" color="primary">
{{ $buttonText }}
</x-mail::button>
@endif

---

Seven Rock Radio | Música y Noticias 24/7
</x-mail::message>
