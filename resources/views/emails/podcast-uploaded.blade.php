@php
    $settings = \App\Models\ThemeSetting::current();
    $ui = $settings->uiTexts();
    $titleText = $ui['email_title_verified_podcast'] ?? 'Servidor de podcast';
    $lblStreaming = $ui['email_label_streaming'] ?? 'Servidor streaming';
    $lblPodcast = $ui['email_label_podcast'] ?? 'Servidor de podcast';
    $footerText = $ui['email_footer_notification'] ?? 'Notificación de que su programa ha sido puesto en la parrilla de la radio.';
    $episodeTitle = trim((string)$episode->live_title) !== '' ? $episode->live_title : '(' . $episode->titulo_programa . ')';
    
    $statusText = $deliveryStatus === 'delivery_verified' ? 'Entrega completa' : ($deliveryStatus === 'delivery_partial' ? 'Entrega parcial' : 'Error en la entrega');
@endphp

<x-mail::message>
# {{ $statusText }}

El episodio de tu programa ha completado su proceso de distribución en la radio.

<x-mail::panel>
**Programa:** {{ $episode->titulo_programa }}  
**Episodio:** #{{ $episode->numero_episodio }}  
**Título:** *{{ $episodeTitle }}*  
**Fecha emisión:** {{ optional($episode->fecha_emision)->format('d/m/Y') ?? 'N/D' }}  
**Archivo MP3:** `{{ basename((string) $localPath) }}`
</x-mail::panel>

<x-mail::table>
| Destino       | Estado | Detalle |
| :---          | :---:  | :--- |
| **{{ $lblStreaming }}** | {{ $radiobossVerified || $archiveVerified ? '✅' : '❌' }} | {{ $radiobossVerified || $archiveVerified ? 'Distribuido correctamente' : 'Con incidencias' }} |
| **Estado general** | {{ $deliveryStatus === 'delivery_verified' ? '✅' : ($deliveryStatus === 'delivery_partial' ? '⚠️' : '❌') }} | {{ $statusText }} |
</x-mail::table>

---

<div style="text-align: center; color: #7b7b7b; font-size: 13px;">
{{ $footerText }}<br>
Este es un correo automático enviado por {{ $settings->site_name }}.
</div>
</x-mail::message>
