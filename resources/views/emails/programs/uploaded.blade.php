<x-mail::message>
# {{ $deliveryStatus === 'verified' ? 'Entrega verificada' : ($deliveryStatus === 'partial' ? 'Entrega parcialmente verificada' : ($deliveryStatus === 'failed' ? 'Entrega con errores' : ($uploadedToRadioboss ? 'Carga y procesamiento exitosos' : 'Procesamiento completado, envío pendiente'))) }}

El sistema procesó el episodio y dejó registro del resultado final.

<x-mail::panel>
**Archivo:** {{ $fileName }}

**RadioBOSS verificado:** {{ $uploadedToRadioboss ? 'Sí' : 'No' }}

**Archive.org verificado:** {{ $archiveVerified ? 'Sí' : 'No' }}

**Estado final:** {{ ucfirst($deliveryStatus) }}
</x-mail::panel>

@if (filled($failureReason))
**Aviso:** {{ $failureReason }}
@endif

@if ($deliveryStatus === 'verified')
La entrega quedó confirmada en los destinos previstos.
@elseif ($deliveryStatus === 'partial')
Una parte de la entrega quedó verificada y otra requiere revisión. El archivo local se conservó como respaldo.
@elseif ($deliveryStatus === 'failed')
No se pudo confirmar la entrega completa. El archivo local se conservó como respaldo.
@else
El episodio quedó procesado y seguirá su flujo normal de verificación.
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
