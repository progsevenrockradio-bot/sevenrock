@php
    $settings = \App\Models\ThemeSetting::current();
    $ui = $settings->uiTexts();
    $titleText = $ui['email_title_verified_podcast'] ?? 'Servidor de podcast';
    $lblPodcast = $ui['email_label_podcast'] ?? 'Servidor de podcast';
    $footerText = $ui['email_footer_notification'] ?? 'Notificación de que su programa ha sido puesto en la parrilla de la radio.';
    $episodeTitle = trim((string)$episode->live_title) !== '' ? $episode->live_title : '(' . $episode->titulo_programa . ')';
@endphp

<x-mail::message>
# {{ $lblPodcast }} verificado

El archivo de tu programa se ha distribuido correctamente a la red de podcasts.

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
| **{{ $lblPodcast }}** | ✅ | Disponible en Archive.org y RSS |
</x-mail::table>

---

<div style="text-align: center; color: #7b7b7b; font-size: 13px;">
{{ $footerText }}<br>
Este es un correo automático enviado por {{ $settings->site_name }}.
</div>
</x-mail::message>
