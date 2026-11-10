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
            <div class="flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                <span>{{ session('success') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
            </div>
        @endif

        {{-- Cabecera --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc] flex items-center gap-3">
                    <span>Incidencias y Rectificaciones Editoriales</span>
                    @if ($counts['urgent'] > 0)
                        <span class="text-xs px-2.5 py-0.5 rounded-full border border-rose-500/40 bg-rose-950/60 text-rose-200 font-sans uppercase font-bold animate-pulse">
                            🚨 {{ $counts['urgent'] }} Urgente(s)
                        </span>
                    @endif
                </h1>
                <p class="mt-1 text-sm text-gray-400">Auditoría de correcciones, fe de erratas, retiradas y cambios de cartel comunicados tras la ingesta.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.editorial.index') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                    ← Volver a Bandeja
                </a>
            </div>
        </div>

        {{-- Pestañas de Estado de Resolución --}}
        <div class="flex flex-wrap gap-2 text-xs font-medium">
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'pending_review']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'pending_review' ? 'bg-amber-600 text-white font-semibold' : 'border border-white/10 bg-white/5 text-amber-300 hover:bg-white/10' }}">
                ⏳ Pendientes de Revisión ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'resolved_applied']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'resolved_applied' ? 'bg-emerald-600 text-white font-semibold' : 'border border-white/10 bg-white/5 text-emerald-400 hover:bg-white/10' }}">
                ✅ Resueltas / Aplicadas ({{ $counts['applied'] }})
            </a>
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'resolved_dismissed']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'resolved_dismissed' ? 'bg-zinc-700 text-white font-semibold' : 'border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10' }}">
                🚫 Descartadas ({{ $counts['dismissed'] }})
            </a>
            <a href="{{ route('admin.editorial.incidents.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'all' ? 'bg-[#c32720] text-white font-semibold' : 'border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10' }}">
                Todas ({{ $counts['all'] }})
            </a>
        </div>

        {{-- Tabla de Incidencias --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="p-3.5 sm:px-5 w-20">ID / Fecha</th>
                            <th class="p-3.5 w-28">Prioridad</th>
                            <th class="p-3.5 w-36">Tipo Incidencia</th>
                            <th class="p-3.5">Noticia Afectada</th>
                            <th class="p-3.5">Acción Cautelar Adoptada</th>
                            <th class="p-3.5 w-32">Resolución</th>
                            <th class="p-3.5 w-28 text-right sm:pr-5">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-gray-300">
                        @forelse ($incidents as $incident)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-3.5 sm:px-5 align-top font-mono text-gray-400">
                                    <div class="font-semibold text-white">#{{ $incident->id }}</div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">{{ $incident->created_at->format('d/m H:i') }}</div>
                                </td>
                                <td class="p-3.5 align-top">
                                    @if ($incident->priority === 'urgent')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-rose-500/40 bg-rose-950/70 text-rose-200 animate-pulse">
                                            🚨 URGENTE
                                        </span>
                                    @elseif ($incident->priority === 'high')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-amber-500/40 bg-amber-950/60 text-amber-200">
                                            ⚠️ ALTA
                                        </span>
                                    @elseif ($incident->priority === 'medium')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-blue-500/40 bg-blue-950/60 text-blue-200">
                                            MEDIA
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-white/10 bg-white/5 text-gray-400">
                                            BAJA
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top font-medium text-gray-300 uppercase text-[10px]">
                                    {{ str_replace('_', ' ', $incident->incident_type) }}
                                </td>
                                <td class="p-3.5 align-top">
                                    @if ($incident->post)
                                        <a href="{{ route('admin.posts.edit', $incident->post) }}" class="text-sm font-medium text-white hover:text-[#c32720] transition-colors block">
                                            {{ $incident->post->title }}
                                        </a>
                                        <div class="text-[11px] text-gray-400 mt-1">
                                            Estado: <span class="uppercase font-semibold text-gray-300">{{ $incident->post->status }}</span>
                                            @if ($incident->post->is_published)
                                                <span class="text-emerald-400 font-semibold ml-1">(Publicada en Web)</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-500 italic">Sin post vinculado directo</span>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top text-xs text-gray-300">
                                    <span class="font-mono text-xs text-gray-400">{{ $incident->action_taken }}</span>
                                    @if (!empty($incident->detected_diff['halted_reason']))
                                        <div class="text-[11px] text-amber-300 mt-1">
                                            {{ $incident->detected_diff['halted_reason'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top">
                                    @if ($incident->resolution_status === 'resolved_applied')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                                            APLICADA
                                        </span>
                                    @elseif ($incident->resolution_status === 'resolved_dismissed')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-white/10 bg-white/5 text-gray-400">
                                            DESCARTADA
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-amber-500/20 bg-amber-500/10 text-amber-300">
                                            PENDIENTE
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 sm:pr-5 align-top text-right">
                                    <button 
                                        type="button" 
                                        @click="openDiff({{ $incident->id }})"
                                        class="inline-flex items-center justify-center h-7 px-2.5 rounded-md border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all"
                                    >
                                        Ver Diffs
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-10 text-center text-gray-500">
                                    No se encontraron incidencias en este estado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($incidents->hasPages())
            <div class="mt-4">
                {{ $incidents->links() }}
            </div>
        @endif

        {{-- MODAL COMPARADOR DE DIFERENCIAS (DIFF VIEWER) & RESOLUCIÓN --}}
        <div x-show="diffModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-2xl rounded-2xl border border-white/10 bg-[#101012] p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-lg uppercase tracking-wider text-white flex items-center gap-2">
                        <span>Comparador de Rectificación</span>
                        <span class="text-xs font-mono text-gray-400" x-text="diffData ? '#' + diffData.id : ''"></span>
                    </h3>
                    <button type="button" @click="diffModalOpen = false" class="text-gray-400 hover:text-white text-lg">✕</button>
                </div>

                <template x-if="diffLoading">
                    <div class="py-12 text-center text-gray-400">
                        <div class="animate-spin inline-block w-6 h-6 border-2 border-current border-t-transparent text-[#c32720] rounded-full"></div>
                        <p class="mt-2 text-xs">Cargando comparativa...</p>
                    </div>
                </template>

                <template x-if="!diffLoading && diffData">
                    <div class="space-y-4 text-xs">
                        {{-- Noticia Afectada --}}
                        <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3.5">
                            <div class="text-[10px] uppercase text-gray-400 font-semibold">Noticia Vinculada</div>
                            <div class="text-sm font-semibold text-white mt-0.5" x-text="diffData.post ? diffData.post.title : 'Sin post vinculado directo'"></div>
                            <div class="text-[11px] text-gray-400 mt-1">
                                Puntuación de Correlación: <strong class="text-gray-200" x-text="diffData.correlation_score + '/100'"></strong>
                            </div>
                        </div>

                        {{-- Diffs Detectados --}}
                        <div class="rounded-xl border border-white/10 bg-black/40 p-3.5 space-y-2">
                            <div class="text-[10px] uppercase text-gray-400 font-semibold">Diferencias Fácticas Detectadas</div>
                            <pre class="bg-black/60 p-3 rounded-lg font-mono text-[11px] text-gray-300 overflow-x-auto whitespace-pre-wrap" x-text="JSON.stringify(diffData.detected_diff, null, 2)"></pre>
                        </div>

                        {{-- Formulario de Resolución Humana --}}
                        <template x-if="diffData.resolution_status === 'pending_review'">
                            <form :action="'/admin/editorial/incidents/' + resolveIncidentId + '/resolve'" method="POST" class="rounded-xl border border-[#c32720]/30 bg-[#c32720]/5 p-4 space-y-3">
                                @csrf
                                <div class="font-display uppercase tracking-wider text-xs font-bold text-white">Resolución Editorial Auditada</div>

                                <div>
                                    <label class="block text-[10px] uppercase text-gray-300 font-semibold mb-1">Acción a Adoptar</label>
                                    <select name="resolution_action" x-model="resolveAction" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                                        <option value="apply_changes">Aplicar Cambios / Fe de Erratas (resolved_applied)</option>
                                        <option value="dismiss">Descartar como Falsa Alarma (resolved_dismissed)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] uppercase text-gray-300 font-semibold mb-1">Notas y Justificación Editorial (Obligatorio)</label>
                                    <textarea name="resolution_notes" x-model="resolveNotes" rows="2" required placeholder="Motivo de la decisión editorial..." class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"></textarea>
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="diffModalOpen = false" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-gray-300 text-xs hover:bg-white/10">Cancelar</button>
                                    <button type="submit" class="inline-flex items-center justify-center h-8 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold">Confirmar Resolución</button>
                                </div>
                            </form>
                        </template>

                        <template x-if="diffData.resolution_status !== 'pending_review'">
                            <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3.5 text-xs space-y-1">
                                <div class="text-[10px] uppercase text-gray-400 font-semibold">Estado de Resolución</div>
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
