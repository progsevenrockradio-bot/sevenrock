<x-mail::message>
# {{ $deliveryStatus === 'verified' ? 'Entrega completa' : ($deliveryStatus === 'partial' ? 'Entrega parcial' : 'Error en la entrega') }}

El episodio {{ $episode->live_title ?: $episode->titulo_programa }} del programa {{ $episode->titulo_programa }} ha terminado su pipeline de entrega.

<x-mail::panel>
**Programa:** {{ $episode->titulo_programa }}

**Episodio:** #{{ $episode->numero_episodio }}

**Título:** {{ $episode->live_title ?: $episode->titulo_programa }}

**Fecha de emisión:** {{ optional($episode->fecha_emision)->format('d/m/Y') ?? 'N/D' }}

**Archivo MP3:** {{ basename((string) $localPath) }}

**Ruta RadioBOSS:** {{ $remotePath }}
</x-mail::panel>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: collapse; margin: 16px 0;">
<tr>
<th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 0;">Destino</th>
<th align="center" style="border-bottom: 1px solid #e5e7eb; padding: 8px 0;">Estado</th>
<th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 0;">Detalle</th>
</tr>
<tr>
<td style="padding: 8px 0;">RadioBOSS</td>
<td align="center" style="padding: 8px 0;">{{ $radiobossVerified ? '✅' : '❌' }}</td>
<td style="padding: 8px 0;">{{ $radiobossVerified ? 'Subido y verificado' : 'Con incidencias o no disponible' }}</td>
</tr>
<tr>
<td style="padding: 8px 0;">Archive.org</td>
<td align="center" style="padding: 8px 0;">{{ $archiveVerified ? '✅' : '❌' }}</td>
<td style="padding: 8px 0;">@if($archiveVerified && !empty($archiveItemUrl))<a href="{{ $archiveItemUrl }}">Ver item</a>@else{{ $archiveVerified ? 'Subido y verificado' : 'Con incidencias o no disponible' }}@endif</td>
</tr>
<tr>
<td style="padding: 8px 0;">Estado general</td>
<td align="center" style="padding: 8px 0;">{{ $deliveryStatus === 'verified' ? '✅' : ($deliveryStatus === 'partial' ? '⚠️' : '❌') }}</td>
<td style="padding: 8px 0;">{{ $deliveryStatus === 'verified' ? 'Entrega completa' : ($deliveryStatus === 'partial' ? 'Entrega parcial' : 'Error en la entrega') }}</td>
</tr>
</table>

Si necesitas revisar el episodio desde el panel, puedes hacerlo desde el área de administración.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
