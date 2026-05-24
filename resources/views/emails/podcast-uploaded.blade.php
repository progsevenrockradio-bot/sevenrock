<x-mail::message>
# Nuevo episodio subido

El episodio {{ $episode->live_title ?: $episode->titulo_programa }} del programa {{ $episode->titulo_programa }} ha sido subido y enviado a RadioBOSS correctamente.

<x-mail::panel>
**Programa:** {{ $episode->titulo_programa }}

**Episodio:** #{{ $episode->numero_episodio }}

**Título:** {{ $episode->live_title ?: $episode->titulo_programa }}

**Fecha de emisión:** {{ optional($episode->fecha_emision)->format('d/m/Y') ?? 'N/D' }}

**Archivo MP3:** {{ basename((string) $localPath) }}

**Ruta RadioBOSS:** {{ $remotePath }}
</x-mail::panel>

Si necesitas revisar el episodio desde el panel, puedes hacerlo desde el área de administración.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
