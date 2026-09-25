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
@if (!empty($unsubscribeUrl))
<div style="margin-top:20px; font-size:11px; color:#666;">
    {{ $senderAddress ?? 'Seven Rock Radio' }}
    <br><br>
    Si no deseas recibir más correos de este tipo, puedes <a href="{{ $unsubscribeUrl }}" style="color:#666;text-decoration:underline;">darte de baja aquí</a>.
</div>
@endif
</x-mail::message>
