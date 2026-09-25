Moderar contenido pendiente

Tipo: {{ \App\Support\ModerationType::tryFrom($item->type)?->label() ?? $item->type }}
Título: {{ $item->title }}
Enviado por: {{ $item->submitter_name }} ({{ $item->submitter_email }}) [IP: {{ $item->submitter_ip }}]
Origen: {{ $item->source }}
Fecha: {{ $item->created_at->format('d/m/Y H:i') }}

@if($item->summary)
Resumen: {{ $item->summary }}
@endif

Para APROBAR visita:
{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('admin.moderation.approve-email', now()->addDays(14), ['item' => $item->id]) }}

Para DENEGAR visita:
{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('admin.moderation.reject-email', now()->addDays(14), ['item' => $item->id]) }}

Ver detalle en el panel:
{{ route('admin.moderation.show', $item) }}
