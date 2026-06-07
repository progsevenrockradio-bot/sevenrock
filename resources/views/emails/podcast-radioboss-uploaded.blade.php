<x-mail::message>
# RadioBOSS verificado

El archivo se subió correctamente al servidor de radio.

<x-mail::panel>
**Programa:** {{ $episode->titulo_programa }}

**Episodio:** #{{ $episode->numero_episodio }}

**Título:** {{ $episode->live_title ?: $episode->titulo_programa }}

**Archivo:** {{ basename((string) $episode->archivo_mp3) }}

**Ruta remota:** {{ $remotePath !== '' ? $remotePath : 'N/D' }}
</x-mail::panel>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: collapse; margin: 16px 0;">
<tr>
<th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 0;">Estado</th>
<th align="left" style="border-bottom: 1px solid #e5e7eb; padding: 8px 0;">Detalle</th>
</tr>
<tr>
<td style="padding: 8px 0;">RB</td>
<td style="padding: 8px 0;">Verificado</td>
</tr>
<tr>
<td style="padding: 8px 0;">Archive</td>
<td style="padding: 8px 0;">{{ in_array($archiveStatus, ['archive_verified', 'archive_pending_indexing'], true) ? 'Verificado' : 'Pendiente' }}</td>
</tr>
<tr>
<td style="padding: 8px 0;">Envío</td>
<td style="padding: 8px 0;">{{ $deliveryStatus === 'delivery_verified' ? 'Verificado' : 'Pendiente' }}</td>
</tr>
</table>

@if ($archiveItemUrl)
Puedes revisar el episodio en Archive.org cuando esté disponible: [Abrir Archive.org]({{ $archiveItemUrl }})
@else
Archive.org sigue pendiente de verificación o indexación.
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
