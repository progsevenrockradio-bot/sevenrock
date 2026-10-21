@php
    $theme = $themeSettings ?? \App\Models\ThemeSetting::current();
@endphp
<x-layouts.admin :title="'Incidencias Editoriales - ' . $theme->site_name">
    <div 
        x-data="{
            diffModalOpen: false,
            diffLoading: false,
            diffData: null,
            resolveIncidentId: null,
            resolveAction: 'apply_changes',
            resolveNotes: '',

            openDiff(incidentId) {
                this.resolveIncidentId = incidentId;
                this.diffModalOpen = true;
                this.diffLoading = true;
                this.diffData = null;
                this.resolveNotes = '';

                fetch(`/admin/editorial/incidents/${incidentId}/diff`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(res => {
                    this.diffLoading = false;
                    if (res.success) {
                        this.diffData = res.data;
                    } else {
                        alert(res.message || 'Error al cargar diferencias');
                        this.diffModalOpen = false;
                    }
                })
                .catch(err => {
                    this.diffLoading = false;
                    alert('Error de conexión');
                    this.diffModalOpen = false;
                });
            }
        }"
        class="space-y-6"
    >
        {{-- Alertas de Sesión --}}
        @if (session('success'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.25)] p-4 text-sm text-[#b8e6c3] flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-[#b8e6c3] hover:text-white">&times;</button>
            </div>
        @endif

        {{-- Cabecera --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-[#2b2b2b] pb-4">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc] flex items-center gap-3">
                    <span>Incidencias y Rectificaciones Editoriales</span>
                    @if ($counts['urgent'] > 0)
                        <span class="text-xs px-2.5 py-0.5 rounded-full border border-red-500 bg-red-950/60 text-red-200 font-sans uppercase font-bold animate-pulse">
                            🚨 {{ $counts['urgent'] }} Urgente(s)
                        </span>
                    @endif
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-[#9a9a9a]">Auditoría de correcciones, fe de erratas, retiradas y cambios de cartel comunicados tras la ingesta.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wider">
                <a href="{{ route('admin.editorial.index') }}" class="px-3 py-2 rounded border border-[#2b2b2b] bg-[#1a1a1c] text-[#9a9a9a] hover:text-white transition-colors">
                    ← Volver a Bandeja
                </a>
            </div>
        </div>

        {{-- Pestañas de Estado de Resolución --}}
        <div class="flex flex-wrap gap-2 border-b border-[#2b2b2b] pb-2 text-xs font-semibold uppercase tracking-wider">
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'pending_review']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'pending_review' ? 'bg-[#b8860b]/30 text-amber-300 border border-[#b8860b]' : 'text-[#888] hover:text-white' }}">
                ⏳ Pendientes de Revisión ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'resolved_applied']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'resolved_applied' ? 'bg-[#1e4d2b] text-[#b8e6c3] border border-[#2d7a44]' : 'text-[#888] hover:text-white' }}">
                ✅ Resueltas / Aplicadas ({{ $counts['applied'] }})
            </a>
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'resolved_dismissed']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'resolved_dismissed' ? 'bg-[#555] text-gray-200' : 'text-[#888] hover:text-white' }}">
                🚫 Descartadas ({{ $counts['dismissed'] }})
            </a>
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'all' ? 'bg-lucille-accent text-white' : 'text-[#888] hover:text-white' }}">
                Todas ({{ $counts['all'] }})
            </a>
        </div>

        {{-- Tabla de Incidencias --}}
        <div class="overflow-x-auto border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] rounded">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-[#2b2b2b] text-[#dcdcdc] font-display uppercase tracking-wider bg-[rgba(0,0,0,.3)]">
                    <tr>
                        <th class="p-3 w-16">ID / Fecha</th>
                        <th class="p-3 w-28">Prioridad</th>
                        <th class="p-3 w-36">Tipo Incidencia</th>
                        <th class="p-3">Noticia Afectada</th>
                        <th class="p-3">Acción Cautelar Adoptada</th>
                        <th class="p-3 w-32">Resolución</th>
                        <th class="p-3 w-28 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2b2b2b] text-[#9a9a9a]">
                    @forelse ($incidents as $incident)
                        <tr class="hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            <td class="p-3 align-top font-mono text-gray-400">
                                <div>#{{ $incident->id }}</div>
                                <div class="text-[10px] text-gray-500">{{ $incident->created_at->format('d/m H:i') }}</div>
                            </td>
                            <td class="p-3 align-top">
                                @if ($incident->priority === 'urgent')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-950/70 text-red-200 border border-red-500 animate-pulse">
                                        🚨 URGENTE
                                    </span>
                                @elseif ($incident->priority === 'high')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950/60 text-amber-200 border border-amber-500">
                                        ⚠️ ALTA
                                    </span>
                                @elseif ($incident->priority === 'medium')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#1e3a5f] text-blue-200 border border-blue-500">
                                        MEDIA
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-white/5 text-gray-400 border border-white/10">
                                        BAJA
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 align-top font-semibold text-gray-200 uppercase text-[10px]">
                                {{ str_replace('_', ' ', $incident->incident_type) }}
                            </td>
                            <td class="p-3 align-top">
                                @if ($incident->post)
                                    <a href="{{ route('admin.posts.edit', $incident->post) }}" class="font-display text-sm uppercase text-gray-200 hover:text-lucille-accent transition-colors block font-semibold">
                                        {{ $incident->post->title }}
                                    </a>
                                    <div class="text-[10px] text-gray-400 mt-0.5">
                                        Estado Post: <span class="uppercase font-bold text-gray-300">{{ $incident->post->status }}</span>
                                        @if ($incident->post->is_published)
                                            <span class="text-green-400 font-bold ml-1">(Publicada en Web)</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-500 italic">Sin post vinculado directo (Ambigua)</span>
                                @endif
                            </td>
                            <td class="p-3 align-top text-[11px] text-gray-300">
                                <span class="font-mono text-gray-400">{{ $incident->action_taken }}</span>
                                @if (!empty($incident->detected_diff['halted_reason']))
                                    <div class="text-[10px] text-amber-300 mt-0.5">
                                        {{ $incident->detected_diff['halted_reason'] }}
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 align-top">
                                @if ($incident->resolution_status === 'resolved_applied')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#1e4d2b] text-[#b8e6c3] border border-[#2d7a44]">
                                        APLICADA
                                    </span>
                                @elseif ($incident->resolution_status === 'resolved_dismissed')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#333] text-gray-300 border border-[#555]">
                                        DESCARTADA
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#b8860b]/20 text-amber-300 border border-[#b8860b]">
                                        PENDIENTE
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 align-top text-right">
                                <button 
                                    type="button" 
                                    @click="openDiff({{ $incident->id }})"
                                    class="lucille-button text-[11px] px-2.5 py-1"
                                >
                                    Ver Diffs
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-[#777]">
                                No se encontraron incidencias en este estado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($incidents->hasPages())
            <div class="mt-4">
                {{ $incidents->links() }}
            </div>
        @endif

        {{-- MODAL COMPARADOR DE DIFERENCIAS (DIFF VIEWER) & RESOLUCIÓN --}}
        <div x-show="diffModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-2xl border border-[#2b2b2b] bg-[#101012] p-6 rounded shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-3">
                    <h3 class="font-display text-lg uppercase tracking-wider text-white flex items-center gap-2">
                        <span>Comparador de Rectificación</span>
                        <span class="text-xs font-mono text-gray-400" x-text="diffData ? '#' + diffData.id : ''"></span>
                    </h3>
                    <button type="button" @click="diffModalOpen = false" class="text-gray-400 hover:text-white text-2xl">&times;</button>
                </div>

                <template x-if="diffLoading">
                    <div class="py-12 text-center text-gray-400">
                        <div class="animate-spin inline-block w-6 h-6 border-2 border-current border-t-transparent text-lucille-accent rounded-full"></div>
                        <p class="mt-2 text-xs">Cargando comparativa...</p>
                    </div>
                </template>

                <template x-if="!diffLoading && diffData">
                    <div class="space-y-4 text-xs">
                        {{-- Noticia Afectada --}}
                        <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-3 rounded">
                            <div class="text-[10px] uppercase text-gray-400 font-bold">Noticia Vinculada</div>
                            <div class="font-display text-base text-white mt-0.5" x-text="diffData.post ? diffData.post.title : 'Sin post vinculado directo'"></div>
                            <div class="text-[10px] text-gray-400 mt-1">
                                Puntuación de Correlación: <strong class="text-gray-200" x-text="diffData.correlation_score + '/100'"></strong>
                            </div>
                        </div>

                        {{-- Diffs Detectados --}}
                        <div class="border border-[#2b2b2b] p-3 rounded space-y-2">
                            <div class="text-[10px] uppercase text-gray-400 font-bold">Diferencias Fácticas Detectadas</div>
                            <pre class="bg-[#141416] p-3 rounded font-mono text-[11px] text-gray-300 overflow-x-auto whitespace-pre-wrap" x-text="JSON.stringify(diffData.detected_diff, null, 2)"></pre>
                        </div>

                        {{-- Formulario de Resolución Humana --}}
                        <template x-if="diffData.resolution_status === 'pending_review'">
                            <form :action="'/admin/editorial/incidents/' + resolveIncidentId + '/resolve'" method="POST" class="border border-lucille-accent/40 bg-lucille-accent/5 p-4 rounded space-y-3">
                                @csrf
                                <div class="font-display uppercase tracking-wider text-xs font-bold text-white">Resolución Editorial Auditada</div>

                                <div>
                                    <label class="block text-[10px] uppercase text-gray-300 font-bold mb-1">Acción a Adoptar</label>
                                    <select name="resolution_action" x-model="resolveAction" required class="lucille-product-field w-full text-xs">
                                        <option value="apply_changes">Aplicar Cambios / Fe de Erratas (resolved_applied)</option>
                                        <option value="dismiss">Descartar como Falsa Alarma (resolved_dismissed)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] uppercase text-gray-300 font-bold mb-1">Notas y Justificación Editorial (Obligatorio)</label>
                                    <textarea name="resolution_notes" x-model="resolveNotes" rows="2" required placeholder="Motivo de la decisión editorial..." class="lucille-product-field w-full text-xs"></textarea>
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="diffModalOpen = false" class="lucille-button text-xs">Cancelar</button>
                                    <button type="submit" class="lucille-button-solid text-xs">Confirmar Resolución</button>
                                </div>
                            </form>
                        </template>

                        <template x-if="diffData.resolution_status !== 'pending_review'">
                            <div class="border border-[#2b2b2b] bg-[#141416] p-3 rounded text-xs space-y-1">
                                <div class="text-[10px] uppercase text-gray-400 font-bold">Estado de Resolución</div>
                                <div>Estado: <strong class="text-white uppercase" x-text="diffData.resolution_status"></strong></div>
                                <div>Fecha: <span class="text-gray-300" x-text="diffData.resolved_at"></span></div>
                                <div>Notas: <span class="text-gray-300 italic" x-text="diffData.resolution_notes"></span></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-layouts.admin>
