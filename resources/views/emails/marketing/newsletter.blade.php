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
@if (!empty($unsubscribeUrl))
<div style="margin-top:20px; font-size:11px; color:#666;">
    {{ $senderAddress ?? 'Seven Rock Radio' }}
    <br><br>
    Si no deseas recibir más correos de este tipo, puedes <a href="{{ $unsubscribeUrl }}" style="color:#666;text-decoration:underline;">darte de baja aquí</a>.
</div>
@endif
</x-mail::message>
