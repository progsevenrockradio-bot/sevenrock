@php
    $settings = \App\Models\ThemeSetting::current();
    $ui = $settings->uiTexts();
    $titleText = $ui['email_title_verified_podcast'] ?? 'Servidor de podcast';
    $lblStreaming = $ui['email_label_streaming'] ?? 'Servidor streaming';
    $footerText = $ui['email_footer_notification'] ?? 'Notificación de que su programa ha sido puesto en la parrilla de la radio.';
    $episodeTitle = trim((string)$episode->live_title) !== '' ? $episode->live_title : '(' . $episode->titulo_programa . ')';
@endphp

<x-mail::message>
# {{ $lblStreaming }} verificado

El archivo de tu programa se ha subido correctamente al servidor de la radio.

<x-mail::panel>
**Programa:** {{ $episode->titulo_programa }}  
**Episodio:** #{{ $episode->numero_episodio }}  
**Título:** *{{ $episodeTitle }}*  
**Programa (Registro original):** {{ $episode->masterProgram?->nombre ?? $episode->titulo_programa }}  
**Archivo:** `{{ basename((string) $episode->archivo_mp3) }}`
</x-mail::panel>

<x-mail::table>
| Destino       | Estado | Detalle |
| :---          | :---:  | :--- |
| **{{ $lblStreaming }}** | ✅ | Subida exitosa a la librería |
</x-mail::table>

---

<div style="text-align: center; color: #7b7b7b; font-size: 13px;">
{{ $footerText }}<br>
Este es un correo automático enviado por {{ $settings->site_name }}.
</div>
</x-mail::message>
