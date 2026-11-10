@php
    $theme = $themeSettings ?? \App\Models\ThemeSetting::current();
    $admin = $themeAppearance['admin_texts'] ?? [];
@endphp
<x-layouts.admin :title="'Bandeja Editorial - ' . $theme->site_name">
    <div 
        x-data="{
            selectedPosts: [],
            selectAllPage: false,
            quickViewOpen: false,
            quickViewLoading: false,
            quickViewData: null,
            quickViewTab: 'summary',
            
            scheduleModalOpen: false,
            scheduleTargetId: null,
            scheduleDate: '',

            rejectModalOpen: false,
            rejectTargetId: null,
            rejectReason: '',

            bulkScheduleOpen: false,
            bulkScheduleDate: '',
            isSubmitting: false,

            toggleAll(event) {
                const checked = event.target.checked;
                const checkboxes = document.querySelectorAll('.post-select-checkbox');
                this.selectedPosts = [];
                checkboxes.forEach(cb => {
                    cb.checked = checked;
                    if (checked) {
                        this.selectedPosts.push(parseInt(cb.value));
                    }
                });
            },

            openQuickView(postId) {
                this.quickViewOpen = true;
                this.quickViewLoading = true;
                this.quickViewData = null;
                this.quickViewTab = 'summary';

                fetch(`/admin/editorial/${postId}/quick-view`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(res => {
                    this.quickViewLoading = false;
                    if (res.success) {
                        this.quickViewData = res.data;
                    } else {
                        alert(res.message || 'Error al cargar vista rápida');
                        this.quickViewOpen = false;
                    }
                })
                .catch(err => {
                    this.quickViewLoading = false;
                    alert('Error al comunicar con el servidor');
                    this.quickViewOpen = false;
                });
            },

            openSchedule(postId, currentScheduledAt = '') {
                this.scheduleTargetId = postId;
                this.scheduleDate = currentScheduledAt;
                this.scheduleModalOpen = true;
            },

            openReject(postId) {
                this.rejectTargetId = postId;
                this.rejectReason = '';
                this.rejectModalOpen = true;
            }
        }"
        class="space-y-6"
    >
        {{-- Alertas de Sesión --}}
        @if (session('success'))
            <div class="flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
            </div>
        @endif

        @if (session('error'))
            <div class="flex items-center justify-between rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-rose-400 hover:text-rose-200 text-xs">✕</button>
            </div>
        @endif

        @if (session('bulk_results'))
            @php $bulk = session('bulk_results'); @endphp
            <div class="rounded-xl border border-white/10 bg-[#101012] p-4 text-sm text-gray-200 space-y-3 shadow-xl">
                <div class="font-display uppercase tracking-wider text-xs font-semibold text-white">Reporte de Acción Masiva</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <div class="p-2.5 rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-300">✅ Aprobadas: <strong>{{ count($bulk['approved']) }}</strong></div>
                    <div class="p-2.5 rounded-lg border border-amber-500/20 bg-amber-500/10 text-amber-300">⏳ Programadas: <strong>{{ count($bulk['scheduled']) }}</strong></div>
                    <div class="p-2.5 rounded-lg border border-rose-500/20 bg-rose-500/10 text-rose-300">❌ Rechazadas: <strong>{{ count($bulk['rejected']) }}</strong></div>
                    <div class="p-2.5 rounded-lg border border-white/10 bg-white/5 text-gray-300">⚠️ Retenidas: <strong>{{ count($bulk['blocked']) }}</strong></div>
                </div>
                @if (!empty($bulk['blocked']))
                    <div class="mt-2 text-xs text-rose-300 space-y-1">
                        <div class="font-medium text-gray-400">Detalle de elementos retenidos por seguridad o conflicto:</div>
                        @foreach ($bulk['blocked'] as $item)
                            <div>• #{{ $item['id'] }} <strong>{{ Str::limit($item['title'], 40) }}</strong>: {{ $item['reason'] }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Cabecera con Pestañas de Navegación --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc] flex items-center gap-3">
                    <span>Bandeja Editorial</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full border border-[#c32720]/40 bg-[#c32720]/15 text-white font-sans uppercase tracking-widest font-bold">Modo A Controlado</span>
                </h1>
                <p class="mt-1 text-sm text-gray-400">Revisión, auditoría y aprobación de notas de prensa recibidas por correo para Seven Rock Radio.</p>
            </div>

            {{-- Sub-navegación entre Bandeja, Fuentes e Incidencias --}}
            <div class="flex flex-wrap gap-2 text-xs font-medium">
                <a href="{{ route('admin.editorial.index') }}" class="px-3 py-1.5 rounded-lg bg-[#c32720] text-white font-semibold shadow-sm">
                    📥 Bandeja ({{ $counts['total'] }})
                </a>
                <a href="{{ route('admin.editorial.sources.index') }}" class="px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10 transition-colors">
                    🏢 Fuentes y Agencias
                </a>
                <a href="{{ route('admin.editorial.incidents.index') }}" class="px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10 transition-colors flex items-center gap-1.5">
                    <span>⚠️ Incidencias</span>
                    @if ($counts['with_incidents'] > 0)
                        <span class="px-1.5 py-0.2 bg-[#c32720] text-white rounded-full text-[10px] font-bold">{{ $counts['with_incidents'] }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.posts.create') }}" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-white font-medium ml-auto sm:ml-2">
                    + Redactar Manual
                </a>
            </div>
        </div>

        {{-- Resumen de Métricas / Chips de Estado --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <a href="{{ route('admin.editorial.index', ['status' => 'all']) }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5 transition-all shadow-sm {{ $currentStatus === 'all' ? 'border-[#c32720] bg-[#c32720]/10' : 'hover:border-white/20' }}">
                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Total Comunicados</div>
                <div class="font-display text-xl text-white mt-1">{{ $counts['total'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'draft']) }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5 transition-all shadow-sm {{ $currentStatus === 'draft' ? 'border-[#c32720] bg-[#c32720]/10' : 'hover:border-white/20' }}">
                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Borradores</div>
                <div class="font-display text-xl text-amber-300 mt-1">{{ $counts['drafts'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'scheduled']) }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5 transition-all shadow-sm {{ $currentStatus === 'scheduled' ? 'border-[#c32720] bg-[#c32720]/10' : 'hover:border-white/20' }}">
                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Programadas</div>
                <div class="font-display text-xl text-blue-300 mt-1">{{ $counts['scheduled'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'published']) }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5 transition-all shadow-sm {{ $currentStatus === 'published' ? 'border-[#c32720] bg-[#c32720]/10' : 'hover:border-white/20' }}">
                <div class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Publicadas</div>
                <div class="font-display text-xl text-emerald-400 mt-1">{{ $counts['published'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['security' => 'active_embargo']) }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5 transition-all shadow-sm {{ $currentSecurity === 'active_embargo' ? 'border-amber-500 bg-amber-500/10' : 'hover:border-white/20' }}">
                <div class="text-[10px] uppercase tracking-wider text-amber-400 font-medium">Bajo Embargo</div>
                <div class="font-display text-xl text-amber-300 mt-1">{{ $counts['embargo_active'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'with_incidents']) }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5 transition-all shadow-sm {{ $currentStatus === 'with_incidents' ? 'border-rose-500 bg-rose-500/10' : 'hover:border-white/20' }}">
                <div class="text-[10px] uppercase tracking-wider text-rose-400 font-medium">Incidencias</div>
                <div class="font-display text-xl text-rose-300 mt-1">{{ $counts['with_incidents'] }}</div>
            </a>
        </div>

        {{-- Barra de Filtros y Búsqueda --}}
        <form method="GET" action="{{ route('admin.editorial.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 rounded-xl border border-white/10 bg-[#101012] p-4 sm:p-5 shadow-xl text-xs">
            {{-- Búsqueda --}}
            <div class="lg:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 font-medium mb-1">Buscar por titular, asunto o remitente</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ $currentSearch }}" 
                        placeholder="Ej. Judas Priest, Sony Music, fe de erratas..."
                        class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none pr-8"
                    >
                    @if ($currentSearch)
                        <a href="{{ route('admin.editorial.index') }}" class="absolute right-2 top-2 text-gray-400 hover:text-white">&times;</a>
                    @endif
                </div>
            </div>

            {{-- Filtro Estado --}}
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 font-medium mb-1">Estado Editorial</label>
                <select name="status" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" onchange="this.form.submit()">
                    <option value="all" {{ $currentStatus === 'all' ? 'selected' : '' }}>Todos los estados</option>
                    <option value="draft" {{ $currentStatus === 'draft' ? 'selected' : '' }}>Borrador (Draft)</option>
                    <option value="scheduled" {{ $currentStatus === 'scheduled' ? 'selected' : '' }}>Programado</option>
                    <option value="published" {{ $currentStatus === 'published' ? 'selected' : '' }}>Publicado</option>
                    <option value="held" {{ $currentStatus === 'held' ? 'selected' : '' }}>Retenido por Seguridad</option>
                    <option value="with_incidents" {{ $currentStatus === 'with_incidents' ? 'selected' : '' }}>Con Incidencia Pendiente</option>
                    <option value="rejected" {{ $currentStatus === 'rejected' ? 'selected' : '' }}>Rechazado</option>
                </select>
            </div>

            {{-- Filtro Seguridad / Embargo --}}
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 font-medium mb-1">Seguridad & Embargo</label>
                <select name="security" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" onchange="this.form.submit()">
                    <option value="all" {{ $currentSecurity === 'all' ? 'selected' : '' }}>Cualquier seguridad</option>
                    <option value="passed" {{ $currentSecurity === 'passed' ? 'selected' : '' }}>Verificado Seguro (Passed)</option>
                    <option value="active_embargo" {{ $currentSecurity === 'active_embargo' ? 'selected' : '' }}>Embargo Activo</option>
                    <option value="needs_review" {{ $currentSecurity === 'needs_review' ? 'selected' : '' }}>Requiere Revisión</option>
                    <option value="held_security" {{ $currentSecurity === 'held_security' ? 'selected' : '' }}>Retenido por Seguridad</option>
                </select>
            </div>

            {{-- Ordenación y Botón --}}
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[10px] uppercase tracking-wider text-gray-400 font-medium mb-1">Ordenar por</label>
                    <select name="sort" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" onchange="this.form.submit()">
                        <option value="newest" {{ $currentSort === 'newest' ? 'selected' : '' }}>Más reciente</option>
                        <option value="oldest" {{ $currentSort === 'oldest' ? 'selected' : '' }}>Más antiguo</option>
                        <option value="embargo" {{ $currentSort === 'embargo' ? 'selected' : '' }}>Próximo Embargo</option>
                        <option value="urgent" {{ $currentSort === 'urgent' ? 'selected' : '' }}>Mayor Urgencia</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium">Filtrar</button>
            </div>
        </form>

        {{-- Barra de Acciones Masivas --}}
        <div 
            x-show="selectedPosts.length > 0" 
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="sticky top-4 z-30 rounded-xl border border-[#c32720]/40 bg-[#101012] shadow-2xl p-4 flex flex-wrap items-center justify-between gap-3"
        >
            <div class="flex items-center gap-3">
                <span class="px-2.5 py-1 bg-[#c32720] text-white text-xs font-bold rounded-md">
                    <span x-text="selectedPosts.length"></span> SELECCIONADOS
                </span>
                <span class="text-xs text-gray-400 hidden sm:inline">Acción coordinada elemento a elemento con validación de seguridad aislada</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Botón Aprobar Masivo --}}
                <form action="{{ route('admin.editorial.bulk-action') }}" method="POST" @submit="isSubmitting = true">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="approve_verified_sources" value="1">
                    <template x-for="id in selectedPosts" :key="id">
                        <input type="hidden" name="post_ids[]" :value="id">
                    </template>
                    <button 
                        type="submit" 
                        :disabled="isSubmitting"
                        class="h-8 px-3 bg-emerald-600/30 border border-emerald-500 text-emerald-300 hover:text-white rounded-lg text-xs font-medium transition-colors disabled:opacity-50"
                        onclick="return confirm('¿Aprobar y publicar las noticias seleccionadas que cumplan los requisitos de seguridad y no tengan embargos ni incidencias pendientes?')"
                    >
                        ✅ Publicar Seleccionadas
                    </button>
                </form>

                {{-- Botón Programar Masivo --}}
                <button 
                    type="button" 
                    @click="bulkScheduleOpen = true"
                    class="h-8 px-3 bg-amber-600/20 border border-amber-500 text-amber-200 hover:text-white rounded-lg text-xs font-medium transition-colors"
                >
                    ⏳ Programar Fecha
                </button>

                {{-- Botón Rechazar Masivo --}}
                <form action="{{ route('admin.editorial.bulk-action') }}" method="POST" @submit="isSubmitting = true">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <template x-for="id in selectedPosts" :key="id">
                        <input type="hidden" name="post_ids[]" :value="id">
                    </template>
                    <button 
                        type="submit" 
                        :disabled="isSubmitting"
                        class="h-8 px-3 bg-rose-950/40 border border-rose-800 text-rose-300 hover:text-white rounded-lg text-xs font-medium transition-colors disabled:opacity-50"
                        onclick="return confirm('¿Rechazar las publicaciones seleccionadas conservando trazabilidad?')"
                    >
                        ❌ Rechazar
                    </button>
                </form>

                <button 
                    type="button" 
                    @click="selectedPosts = []; document.querySelectorAll('.post-select-checkbox').forEach(c => c.checked = false); selectAllPage = false;"
                    class="h-8 px-2.5 text-xs text-gray-400 hover:text-white"
                >
                    Limpiar
                </button>
            </div>
        </div>

        {{-- Tabla Principal de Comunicados --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="p-3.5 w-8 text-center">
                                <input 
                                    type="checkbox" 
                                    x-model="selectAllPage" 
                                    @change="toggleAll($event)"
                                    class="rounded border-white/20 bg-black/40 text-[#c32720] focus:ring-[#c32720] cursor-pointer"
                                    title="Seleccionar todos los elementos de esta página"
                                >
                            </th>
                            <th class="p-3.5 w-20">Portada</th>
                            <th class="p-3.5">Titular Propuesto & Resumen Editorial</th>
                            <th class="p-3.5 w-48">Agencia / Sello</th>
                            <th class="p-3.5 w-32">Multimedia</th>
                            <th class="p-3.5 w-36">Estado / Alertas</th>
                            <th class="p-3.5 w-36 text-right sm:pr-5">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-gray-300">
                        @forelse ($posts as $post)
                            @php
                                $meta = $post->sanitizedMeta;
                                $hasActiveEmbargo = $meta && $meta->is_embargo_active && $meta->embargo_until?->isFuture();
                                $pendingIncident = $post->incidents->first();
                                $mediaInv = app(\App\Services\EmailMediaInventoryExtractor::class)->getEffectiveInventory($meta?->media_inventory, is_array($post->content) ? json_encode($post->content) : (string) $post->content);
                            @endphp
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                {{-- Checkbox individual --}}
                                <td class="p-3.5 text-center align-middle">
                                    <input 
                                        type="checkbox" 
                                        value="{{ $post->id }}" 
                                        class="post-select-checkbox rounded border-white/20 bg-black/40 text-[#c32720] focus:ring-[#c32720] cursor-pointer"
                                        @change="
                                            if ($el.checked) {
                                                if (!selectedPosts.includes({{ $post->id }})) selectedPosts.push({{ $post->id }});
                                            } else {
                                                selectedPosts = selectedPosts.filter(i => i !== {{ $post->id }});
                                                selectAllPage = false;
                                            }
                                        "
                                    >
                                </td>

                                {{-- Portada --}}
                                <td class="p-3.5 align-top">
                                    @if ($post->featured_image_url)
                                        <img src="{{ $post->featured_image_url }}" alt="Portada" class="w-16 h-16 object-cover rounded-lg border border-white/10 bg-black">
                                    @else
                                        <div class="w-16 h-16 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-gray-500 text-xs font-mono">
                                            Sin Portada
                                        </div>
                                    @endif
                                </td>

                                {{-- Titular y Resumen --}}
                                <td class="p-3.5 align-top">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-white hover:text-[#c32720] transition-colors cursor-pointer" @click="openQuickView({{ $post->id }})">
                                            {{ $post->title }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-gray-400 line-clamp-2 text-xs leading-relaxed">
                                        {{ $post->excerpt ?: Str::limit(strip_tags(is_array($post->content) ? json_encode($post->content) : $post->content), 140) }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-gray-500">
                                        <span>Recibido: <strong class="text-gray-300" title="{{ $post->created_at->format('d/m/Y H:i:s') }}">{{ $post->created_at->diffForHumans() }}</strong></span>
                                        @if ($post->source_subject)
                                            <span>• Asunto Original: <em class="text-gray-400">"{{ Str::limit($post->source_subject, 40) }}"</em></span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Agencia / Remitente --}}
                                <td class="p-3.5 align-top">
                                    @php
                                        $sourceInfo = app(\App\Services\EmailSenderSourceResolver::class)->resolve((string) ($post->author_email ?? $meta?->sender_email));
                                    @endphp
                                    <div class="font-medium text-white text-xs">
                                        {{ $sourceInfo['source']?->name ?? 'Remitente No Registrado' }}
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-0.5 truncate max-w-[180px]" title="{{ $post->author_email }}">
                                        {{ $post->author_email ?: 'Sin remitente' }}
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-1.5">
                                        @if ($sourceInfo['status'] === 'authorized')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                                                🛡️ AUTORIZADA
                                            </span>
                                        @elseif ($sourceInfo['status'] === 'pending')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20">
                                                ⏳ PENDIENTE
                                            </span>
                                        @elseif ($sourceInfo['status'] === 'blocked')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-500/10 text-rose-300 border border-rose-500/20">
                                                🚫 BLOQUEADA
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-white/5 text-gray-400 border border-white/10">
                                                ℹ️ NO REGISTRADA
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Contadores Multimedia Reales --}}
                                <td class="p-3.5 align-top">
                                    <div class="space-y-1 text-xs">
                                        <div class="flex items-center justify-between" title="Fotografías adjuntas vs enlazadas">
                                            <span class="text-gray-400">📷 Fotos:</span>
                                            <strong class="text-white">
                                                {{ $mediaInv['attached_photos_count'] !== null ? $mediaInv['attached_photos_count'] : 'N/D' }} / {{ $mediaInv['linked_photos_count'] }}
                                            </strong>
                                        </div>
                                        <div class="flex items-center justify-between" title="Vídeos enlazados">
                                            <span class="text-gray-400">📹 Vídeos:</span>
                                            <strong class="text-white">{{ $mediaInv['linked_videos_count'] }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between" title="Audios MP3 adjuntos vs plataformas">
                                            <span class="text-gray-400">🎵 Audios:</span>
                                            <strong class="text-white">
                                                {{ $mediaInv['attached_mp3s_count'] !== null ? $mediaInv['attached_mp3s_count'] : 'N/D' }}
                                            </strong>
                                        </div>
                                        <div class="flex items-center justify-between" title="Documentos o dossiers">
                                            <span class="text-gray-400">📄 Docs:</span>
                                            <strong class="text-white">
                                                {{ $mediaInv['attached_docs_count'] !== null ? $mediaInv['attached_docs_count'] : 'N/D' }}
                                            </strong>
                                        </div>
                                    </div>
                                </td>

                                {{-- Estado y Alertas --}}
                                <td class="p-3.5 align-top space-y-1.5">
                                    <div>
                                        @if ($post->status === 'published' || $post->is_published)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                                                PUBLICADO
                                            </span>
                                        @elseif ($post->status === 'scheduled')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-500/10 text-blue-300 border border-blue-500/20">
                                                ⏳ PROGRAMADO
                                            </span>
                                        @elseif ($post->status === 'rejected')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-rose-500/10 text-rose-300 border border-rose-500/20">
                                                RECHAZADO
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/5 text-gray-300 border border-white/10">
                                                BORRADOR
                                            </span>
                                        @endif
                                    </div>

                                    @if ($hasActiveEmbargo)
                                        <div class="inline-flex items-center gap-1 text-[10px] font-medium text-amber-300 bg-amber-950/40 border border-amber-600/50 px-1.5 py-0.5 rounded" title="Material bajo embargo de prensa">
                                            <span>⏳ EMBARGO:</span>
                                            <span>{{ $meta->embargo_until->format('d/m H:i') }}</span>
                                        </div>
                                    @endif

                                    @if ($pendingIncident)
                                        <div class="inline-flex items-center gap-1 text-[10px] font-medium text-rose-300 bg-rose-950/40 border border-rose-600/50 px-1.5 py-0.5 rounded animate-pulse" title="Rectificación pendiente">
                                            <span>🚨 INCIDENCIA</span>
                                        </div>
                                    @endif
                                </td>

                                {{-- Acciones --}}
                                <td class="p-3.5 sm:pr-5 align-top text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button 
                                            type="button" 
                                            @click="openQuickView({{ $post->id }})"
                                            class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-white/10 bg-white/5 hover:bg-white/10 text-gray-300"
                                            title="Vista Rápida"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>

                                        <a 
                                            href="{{ route('admin.posts.edit', $post) }}" 
                                            class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-white/10 bg-white/5 hover:bg-white/10 text-gray-300"
                                            title="Editar Noticia Completa"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </a>

                                        @if (!$post->is_published && $post->status !== 'published')
                                            <form action="{{ route('admin.editorial.approve', $post) }}" method="POST" class="inline" onsubmit="return confirm('¿Aprobar y publicar inmediatamente esta noticia?')">
                                                @csrf
                                                <input type="hidden" name="approve_verified_sources" value="1">
                                                <button 
                                                    type="submit" 
                                                    class="inline-flex items-center justify-center h-7 px-2 rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20 text-xs font-medium"
                                                    title="Aprobar y Publicar Ahora"
                                                >
                                                    Publicar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-12 text-center text-gray-500">
                                    No se encontraron noticias en la bandeja editorial.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($posts->hasPages())
            <div class="mt-4">
                {{ $posts->links() }}
            </div>
        @endif

        {{-- DRAWER LATERAL DE VISTA RÁPIDA (QUICK VIEW) --}}
        <div 
            x-show="quickViewOpen" 
            x-cloak
            class="fixed inset-0 z-50 overflow-hidden bg-black/80 backdrop-blur-sm"
            @keydown.escape.window="quickViewOpen = false"
        >
            <div class="absolute inset-y-0 right-0 max-w-full flex pl-10">
                <div 
                    class="w-screen max-w-2xl border-l border-white/10 bg-[#101012] p-6 shadow-2xl overflow-y-auto space-y-5"
                    @click.away="quickViewOpen = false"
                >
                    <div class="flex items-center justify-between border-b border-white/10 pb-4">
                        <div>
                            <span class="text-[10px] uppercase font-bold tracking-widest text-[#c32720]">Bandeja Editorial</span>
                            <h2 class="font-display text-lg uppercase tracking-wider text-white mt-0.5">Vista Rápida de Noticia</h2>
                        </div>
                        <button type="button" @click="quickViewOpen = false" class="text-gray-400 hover:text-white text-lg">✕</button>
                    </div>

                    <template x-if="quickViewLoading">
                        <div class="py-16 text-center text-gray-400">
                            <div class="animate-spin inline-block w-6 h-6 border-2 border-current border-t-transparent text-[#c32720] rounded-full"></div>
                            <p class="mt-2 text-xs">Cargando detalles del comunicado...</p>
                        </div>
                    </template>

                    <template x-if="!quickViewLoading && quickViewData">
                        <div class="space-y-5 text-xs">
                            {{-- Pestañas del Drawer --}}
                            <div class="flex border-b border-white/10 text-xs font-medium">
                                <button type="button" @click="quickViewTab = 'summary'" :class="quickViewTab === 'summary' ? 'border-b-2 border-[#c32720] text-white' : 'text-gray-400 hover:text-white'" class="pb-2 px-3">Resumen</button>
                                <button type="button" @click="quickViewTab = 'content'" :class="quickViewTab === 'content' ? 'border-b-2 border-[#c32720] text-white' : 'text-gray-400 hover:text-white'" class="pb-2 px-3">Contenido</button>
                                <button type="button" @click="quickViewTab = 'media'" :class="quickViewTab === 'media' ? 'border-b-2 border-[#c32720] text-white' : 'text-gray-400 hover:text-white'" class="pb-2 px-3">Multimedia</button>
                                <button type="button" @click="quickViewTab = 'security'" :class="quickViewTab === 'security' ? 'border-b-2 border-[#c32720] text-white' : 'text-gray-400 hover:text-white'" class="pb-2 px-3">Auditoría IA</button>
                            </div>

                            {{-- Pestaña 1: Resumen --}}
                            <div x-show="quickViewTab === 'summary'" class="space-y-4">
                                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4 space-y-2">
                                    <div class="text-[10px] uppercase text-gray-400 font-semibold">Titular</div>
                                    <div class="text-base font-semibold text-white" x-text="quickViewData.title"></div>
                                    <div class="text-xs text-gray-300 mt-2" x-text="quickViewData.excerpt"></div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                                        <div class="text-[10px] uppercase text-gray-400 font-semibold">Remitente</div>
                                        <div class="text-white mt-1" x-text="quickViewData.author_email"></div>
                                    </div>
                                    <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                                        <div class="text-[10px] uppercase text-gray-400 font-semibold">Estado</div>
                                        <div class="text-white mt-1 uppercase" x-text="quickViewData.status"></div>
                                    </div>
                                </div>

                                <template x-if="quickViewData.meta && quickViewData.meta.editorial_notes">
                                    <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4">
                                        <div class="text-[10px] uppercase text-gray-400 font-semibold">Notas Editoriales</div>
                                        <div class="text-gray-300 mt-1" x-text="quickViewData.meta.editorial_notes"></div>
                                    </div>
                                </template>
                            </div>

                            {{-- Pestaña 2: Contenido --}}
                            <div x-show="quickViewTab === 'content'" class="space-y-4 text-xs">
                                <template x-if="quickViewData.featured_image">
                                    <div class="rounded-xl border border-white/10 overflow-hidden">
                                        <img :src="quickViewData.featured_image" class="w-full h-48 object-cover">
                                    </div>
                                </template>
                                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4 space-y-3">
                                    <div class="text-[10px] uppercase text-gray-400 font-semibold">Vista Previa de Redacción</div>
                                    <div class="text-gray-300 leading-relaxed whitespace-pre-line" x-text="quickViewData.content_preview"></div>
                                </div>
                            </div>

                            {{-- Pestaña 3: Multimedia & Enlaces --}}
                            <div x-show="quickViewTab === 'media'" class="space-y-4 text-xs">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl border border-white/10 p-3 bg-white/[0.02]">
                                        <div class="text-[10px] uppercase text-gray-400 font-semibold">Fotografías</div>
                                        <div class="text-white mt-1" x-text="(quickViewData.media_inventory.attached_photos_count !== null ? quickViewData.media_inventory.attached_photos_count : 'N/D') + ' adjuntas / ' + quickViewData.media_inventory.linked_photos_count + ' enlazadas'"></div>
                                    </div>
                                    <div class="rounded-xl border border-white/10 p-3 bg-white/[0.02]">
                                        <div class="text-[10px] uppercase text-gray-400 font-semibold">Audio / MP3</div>
                                        <div class="text-white mt-1" x-text="(quickViewData.media_inventory.attached_mp3s_count !== null ? quickViewData.media_inventory.attached_mp3s_count : 'N/D') + ' MP3 / ' + quickViewData.media_inventory.linked_music_count + ' enlaces'"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Pestaña 4: Auditoría & Telemetría IA --}}
                            <div x-show="quickViewTab === 'security'" class="space-y-4 text-xs">
                                <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02] space-y-2">
                                    <div class="text-[10px] uppercase text-gray-400 font-semibold">Verificación del Remitente</div>
                                    <div>Agencia: <strong class="text-white" x-text="quickViewData.source.name"></strong></div>
                                    <div>Correo: <span class="text-gray-300" x-text="quickViewData.author_email"></span></div>
                                    <div>Estado: <span class="font-bold uppercase tracking-wider" x-text="quickViewData.source.status"></span></div>
                                </div>

                                <template x-if="quickViewData.meta && quickViewData.meta.sources && quickViewData.meta.sources.length > 0">
                                    <div class="rounded-xl border border-white/10 p-4 bg-white/[0.02] space-y-2">
                                        <div class="text-[10px] uppercase text-gray-400 font-semibold">Fuentes Consultadas y Aprobación Editorial</div>
                                        <ul class="space-y-1.5 mt-2">
                                            <template x-for="sourceUrl in quickViewData.meta.sources" :key="sourceUrl">
                                                <li class="flex items-center justify-between gap-2 p-1.5 rounded border border-white/5 bg-white/[0.01]">
                                                    <span class="text-gray-300 font-mono text-[11px] truncate" x-text="sourceUrl"></span>
                                                    <span 
                                                        class="text-[10px] px-1.5 py-0.5 rounded font-semibold shrink-0"
                                                        :class="(quickViewData.meta.approved_urls && quickViewData.meta.approved_urls.includes(sourceUrl)) ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
                                                        x-text="(quickViewData.meta.approved_urls && quickViewData.meta.approved_urls.includes(sourceUrl)) ? 'Aprobada' : 'Pendiente'"
                                                    ></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </div>

                            {{-- Botones de Acción al pie del Drawer --}}
                            <div class="border-t border-white/10 pt-4 flex flex-wrap items-center justify-between gap-3">
                                <a :href="'/admin/posts/' + quickViewData.id + '/edit'" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium">
                                    ✏️ Abrir Editor Completo
                                </a>

                                <div class="flex items-center gap-2">
                                    <form :action="'/admin/editorial/' + quickViewData.id + '/approve'" method="POST" onsubmit="return confirm('¿Aprobar y publicar ahora?')">
                                        @csrf
                                        <input type="hidden" name="approve_verified_sources" value="1">
                                        <button type="submit" class="inline-flex items-center justify-center h-8 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold">
                                            ✅ Publicar Ahora
                                        </button>
                                    </form>

                                    <button type="button" @click="quickViewOpen = false; openSchedule(quickViewData.id)" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium">
                                        ⏳ Programar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- MODAL INDIVIDUAL: PROGRAMAR PUBLICACIÓN --}}
        <div x-show="scheduleModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#101012] p-6 shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Programar Publicación</h3>
                <p class="text-xs text-gray-400">Selecciona la fecha y hora futura para publicar esta noticia automáticamente.</p>

                <form :action="'/admin/editorial/' + scheduleTargetId + '/schedule'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Fecha y Hora de Publicación</label>
                        <input type="datetime-local" name="scheduled_at" x-model="scheduleDate" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="scheduleModalOpen = false" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-gray-300 text-xs hover:bg-white/10">Cancelar</button>
                        <button type="submit" class="inline-flex items-center justify-center h-8 px-4 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold">Confirmar Programación</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL INDIVIDUAL: RECHAZAR NOTICIA --}}
        <div x-show="rejectModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#101012] p-6 shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Rechazar Noticia</h3>
                <p class="text-xs text-gray-400">La noticia cambiará a estado rechazado sin eliminarse, garantizando trazabilidad.</p>

                <form :action="'/admin/editorial/' + rejectTargetId + '/reject'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Motivo del Rechazo</label>
                        <input type="text" name="reason" x-model="rejectReason" placeholder="Ej. Fuera de línea editorial, spam, información obsoleta..." required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="rejectModalOpen = false" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-gray-300 text-xs hover:bg-white/10">Cancelar</button>
                        <button type="submit" class="inline-flex items-center justify-center h-8 px-4 rounded-lg bg-rose-900 hover:bg-rose-800 text-rose-200 text-xs font-semibold">Confirmar Rechazo</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL MASIVO: PROGRAMAR PUBLICACIÓN SELECCIONADAS --}}
        <div x-show="bulkScheduleOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#101012] p-6 shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Programar Selección Masiva</h3>
                <p class="text-xs text-gray-400">Se programarán <strong x-text="selectedPosts.length"></strong> noticias para la fecha especificada.</p>

                <form action="{{ route('admin.editorial.bulk-action') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="action" value="schedule">
                    <template x-for="id in selectedPosts" :key="id">
                        <input type="hidden" name="post_ids[]" :value="id">
                    </template>

                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Fecha y Hora Programada</label>
                        <input type="datetime-local" name="scheduled_at" x-model="bulkScheduleDate" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="bulkScheduleOpen = false" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-gray-300 text-xs hover:bg-white/10">Cancelar</button>
                        <button type="submit" class="inline-flex items-center justify-center h-8 px-4 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold">Programar Todas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
