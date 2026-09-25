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
@if (!empty($unsubscribeUrl))
<div style="margin-top:20px; font-size:11px; color:#666;">
    {{ $senderAddress ?? 'Seven Rock Radio' }}
    <br><br>
    Si no deseas recibir más correos de este tipo, puedes <a href="{{ $unsubscribeUrl }}" style="color:#666;text-decoration:underline;">darte de baja aquí</a>.
</div>
@endif
</x-mail::message>
