<x-layouts.admin title="Revisar Solicitud - {{ $item->title }}">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Revisar Solicitud</h1>
            <p class="mt-2 text-[#9a9a9a]">{{ $item->title }}</p>
        </div>
        <a href="{{ route('admin.moderation.index', ['status' => $item->status]) }}" class="lucille-button self-start">
            &larr; Volver al buzón
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Detalles Principales -->
        <div class="lg:col-span-2 space-y-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <h2 class="mb-4 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2">Resumen</h2>
                <div class="space-y-4">
                    <div>
                        <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Tipo de contenido</span>
                        <span class="inline-block mt-1 bg-[#1a1a1c] border border-[#2b2b2b] px-2 py-1 text-xs font-bold uppercase tracking-wider text-[#dcdcdc]">
                            {{ str_replace('_', ' ', $item->type) }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Resumen del usuario</span>
                        <p class="mt-1 text-sm text-[#dcdcdc] bg-[#1a1a1c] p-3 border border-[#2b2b2b] whitespace-pre-wrap">{{ $item->summary }}</p>
                    </div>
                </div>
            </div>

            @if($item->payload)
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                    <h2 class="mb-4 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2">Datos Completos (Payload)</h2>
                    <div class="bg-[#1a1a1c] border border-[#2b2b2b] p-4 overflow-x-auto">
                        <pre class="text-xs text-[#dcdcdc]">@json($item->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</pre>
                    </div>
                </div>
            @endif
        </div>

        <!-- Columna lateral: Remitente y Acciones -->
        <div class="space-y-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <h2 class="mb-4 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2">Remitente</h2>
                <div class="space-y-3">
                    <div>
                        <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre</span>
                        <div class="mt-1 text-sm text-[#dcdcdc]">{{ $item->submitter_name }}</div>
                    </div>
                    @if($item->submitter_email)
                        <div>
                            <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo</span>
                            <div class="mt-1 text-sm text-[#dcdcdc]">
                                <a href="mailto:{{ $item->submitter_email }}" class="text-[#c32720] hover:underline">{{ $item->submitter_email }}</a>
                            </div>
                        </div>
                    @endif
                    <div>
                        <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de envío</span>
                        <div class="mt-1 text-sm text-[#dcdcdc]">{{ $item->created_at->format('d M Y, H:i') }} ({{ $item->created_at->diffForHumans() }})</div>
                    </div>
                </div>
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <h2 class="mb-4 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2">Estado y Resolución</h2>
                
                <div class="mb-6">
                    <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b] mb-2">Estado actual</span>
                    @if($item->status === 'pending')
                        <span class="inline-flex items-center gap-2 bg-[#b38200] text-black px-3 py-1 text-sm font-bold uppercase tracking-wider">
                            ⏳ Pendiente
                        </span>
                    @elseif($item->status === 'approved')
                        <span class="inline-flex items-center gap-2 bg-[#1e4d2b] text-[#b8e6c3] px-3 py-1 text-sm font-bold uppercase tracking-wider border border-[#1e4d2b]">
                            ✅ Aprobado
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 bg-[#7a2b2b] text-[#ff9e9e] px-3 py-1 text-sm font-bold uppercase tracking-wider border border-[#7a2b2b]">
                            ❌ Denegado
                        </span>
                    @endif
                </div>

                @if($item->status === 'pending')
                    <div class="space-y-4">
                        <form action="{{ route('admin.moderation.approve', $item) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b] mb-1">Nota (Opcional)</label>
                                <textarea name="note" rows="2" class="w-full border border-[#2b2b2b] bg-[#1a1a1c] px-3 py-2 text-sm text-[#dcdcdc] focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" placeholder="Razón o nota interna..."></textarea>
                            </div>
                            <button type="submit" class="w-full lucille-button-solid bg-[#1e4d2b] border-[#1e4d2b] hover:bg-[#163820] flex justify-center py-3">
                                Aprobar Contenido
                            </button>
                        </form>
                        
                        <form action="{{ route('admin.moderation.reject', $item) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b] mb-1">Nota (Opcional)</label>
                                <textarea name="note" rows="2" class="w-full border border-[#2b2b2b] bg-[#1a1a1c] px-3 py-2 text-sm text-[#dcdcdc] focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" placeholder="Razón del rechazo..."></textarea>
                            </div>
                            <button type="submit" class="w-full lucille-button-solid bg-[#7a2b2b] border-[#7a2b2b] hover:bg-[#5c2020] flex justify-center py-3">
                                Denegar Contenido
                            </button>
                        </form>
                    </div>
                @else
                    <div class="space-y-3">
                        <div>
                            <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Moderado por</span>
                            <div class="mt-1 text-sm text-[#dcdcdc]">{{ $item->moderator->name ?? 'Sistema / Email' }}</div>
                        </div>
                        <div>
                            <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de resolución</span>
                            <div class="mt-1 text-sm text-[#dcdcdc]">{{ $item->moderated_at ? $item->moderated_at->format('d M Y, H:i') : '-' }}</div>
                        </div>
                        @if($item->admin_notes)
                            <div>
                                <span class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nota</span>
                                <p class="mt-1 text-sm text-[#dcdcdc] bg-[#1a1a1c] p-2 border border-[#2b2b2b]">{{ $item->admin_notes }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
