<div>
    <h2>Moderar contenido pendiente</h2>
    <p><strong>Tipo:</strong> {{ \App\Support\ModerationType::tryFrom($item->type)?->label() ?? $item->type }}</p>
    <p><strong>Título:</strong> {{ $item->title }}</p>
    <p><strong>Enviado por:</strong> {{ $item->submitter_name }} ({{ $item->submitter_email }}) [IP: {{ $item->submitter_ip }}]</p>
    <p><strong>Origen:</strong> {{ $item->source }}</p>
    <p><strong>Fecha:</strong> {{ $item->created_at->format('d/m/Y H:i') }}</p>
    
    @if($item->summary)
        <p><strong>Resumen:</strong> {{ $item->summary }}</p>
    @endif

    <div style="margin-top: 30px; display: flex; gap: 20px;">
        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('admin.moderation.approve-email', now()->addDays(14), ['item' => $item->id]) }}" 
           style="padding: 15px 30px; background-color: #4CAF50; color: white; text-decoration: none; font-size: 18px; border-radius: 5px; display: inline-block;">
           APROBAR
        </a>
        
        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('admin.moderation.reject-email', now()->addDays(14), ['item' => $item->id]) }}" 
           style="padding: 15px 30px; background-color: #f44336; color: white; text-decoration: none; font-size: 18px; border-radius: 5px; display: inline-block;">
           DENEGAR
        </a>
    </div>

    <p style="margin-top: 30px;">
        <a href="{{ route('admin.moderation.show', $item) }}">Ver en el panel</a>
    </p>
</div>
