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
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.25)] p-4 text-sm text-[#b8e6c3] flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#4ade80]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-[#b8e6c3] hover:text-white">&times;</button>
            </div>
        @endif

        @if (session('error'))
            <div class="border border-[#7a2b2b] bg-[rgba(122,43,43,.25)] p-4 text-sm text-[#fca5a5] flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#f87171]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="$el.parentElement.remove()" class="text-[#fca5a5] hover:text-white">&times;</button>
            </div>
        @endif

        @if (session('bulk_results'))
            @php $bulk = session('bulk_results'); @endphp
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.95)] p-4 text-sm text-[#dcdcdc] space-y-2">
                <div class="font-display uppercase tracking-wider text-xs font-semibold text-[#f2f2f2]">Reporte de Acción Masiva</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <div class="p-2 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] rounded">✅ Aprobadas: <strong>{{ count($bulk['approved']) }}</strong></div>
                    <div class="p-2 border border-[#b8860b] bg-[rgba(184,134,11,.15)] rounded">⏳ Programadas: <strong>{{ count($bulk['scheduled']) }}</strong></div>
                    <div class="p-2 border border-[#7a2b2b] bg-[rgba(122,43,43,.2)] rounded">❌ Rechazadas: <strong>{{ count($bulk['rejected']) }}</strong></div>
                    <div class="p-2 border border-[#555] bg-[rgba(255,255,255,.05)] rounded">⚠️ Bloqueadas / Retenidas: <strong>{{ count($bulk['blocked']) }}</strong></div>
                </div>
                @if (!empty($bulk['blocked']))
                    <div class="mt-2 text-xs text-[#fca5a5] space-y-1">
                        <div class="font-semibold text-gray-400">Detalle de elementos retenidos por seguridad o conflicto:</div>
                        @foreach ($bulk['blocked'] as $item)
                            <div>• #{{ $item['id'] }} <strong>{{ Str::limit($item['title'], 40) }}</strong>: {{ $item['reason'] }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Cabecera con Pestañas de Navegación --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-[#2b2b2b] pb-4">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc] flex items-center gap-3">
                    <span>Bandeja Editorial</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full border border-lucille-accent/40 bg-lucille-accent/15 text-white font-sans uppercase tracking-widest font-bold">Modo A Controlado</span>
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-[#9a9a9a]">Revisión, auditoría y aprobación de notas de prensa y comunicados recibidos por correo.</p>
            </div>

            {{-- Sub-navegación entre Bandeja, Fuentes e Incidencias --}}
            <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wider">
                <a href="{{ route('admin.editorial.index') }}" class="px-3 py-2 rounded border border-lucille-accent bg-lucille-accent/20 text-white">
                    📥 Bandeja ({{ $counts['total'] }})
                </a>
                <a href="{{ route('admin.editorial.sources.index') }}" class="px-3 py-2 rounded border border-[#2b2b2b] bg-[#1a1a1c] text-[#9a9a9a] hover:text-white hover:border-[#444] transition-colors">
                    🏢 Fuentes y Agencias
                </a>
                <a href="{{ route('admin.editorial.incidents.index') }}" class="px-3 py-2 rounded border border-[#2b2b2b] bg-[#1a1a1c] text-[#9a9a9a] hover:text-white hover:border-[#444] transition-colors flex items-center gap-1.5">
                    <span>⚠️ Incidencias</span>
                    @if ($counts['with_incidents'] > 0)
                        <span class="px-1.5 py-0.2 bg-[#c32720] text-white rounded-full text-[10px] font-bold">{{ $counts['with_incidents'] }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.posts.create') }}" class="lucille-button-solid text-xs ml-auto sm:ml-2">
                    + Redactar Manual
                </a>
            </div>
        </div>

        {{-- Resumen de Métricas / Chips de Estado --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <a href="{{ route('admin.editorial.index', ['status' => 'all']) }}" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-3 rounded transition-colors {{ $currentStatus === 'all' ? 'border-lucille-accent bg-lucille-accent/10' : 'hover:border-[#444]' }}">
                <div class="text-[10px] uppercase tracking-wider text-[#8a8a8a]">Total Comunicados</div>
                <div class="font-display text-xl text-[#f2f2f2] mt-1">{{ $counts['total'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'draft']) }}" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-3 rounded transition-colors {{ $currentStatus === 'draft' ? 'border-lucille-accent bg-lucille-accent/10' : 'hover:border-[#444]' }}">
                <div class="text-[10px] uppercase tracking-wider text-[#9a9a9a]">Borradores</div>
                <div class="font-display text-xl text-[#d4a843] mt-1">{{ $counts['drafts'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'scheduled']) }}" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-3 rounded transition-colors {{ $currentStatus === 'scheduled' ? 'border-lucille-accent bg-lucille-accent/10' : 'hover:border-[#444]' }}">
                <div class="text-[10px] uppercase tracking-wider text-[#9a9a9a]">Programadas</div>
                <div class="font-display text-xl text-[#60a5fa] mt-1">{{ $counts['scheduled'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'published']) }}" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-3 rounded transition-colors {{ $currentStatus === 'published' ? 'border-lucille-accent bg-lucille-accent/10' : 'hover:border-[#444]' }}">
                <div class="text-[10px] uppercase tracking-wider text-[#9a9a9a]">Publicadas</div>
                <div class="font-display text-xl text-[#4ade80] mt-1">{{ $counts['published'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['security' => 'active_embargo']) }}" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-3 rounded transition-colors {{ $currentSecurity === 'active_embargo' ? 'border-amber-500 bg-amber-500/10' : 'hover:border-[#444]' }}">
                <div class="text-[10px] uppercase tracking-wider text-amber-400">Bajo Embargo</div>
                <div class="font-display text-xl text-amber-300 mt-1">{{ $counts['embargo_active'] }}</div>
            </a>
            <a href="{{ route('admin.editorial.index', ['status' => 'with_incidents']) }}" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-3 rounded transition-colors {{ $currentStatus === 'with_incidents' ? 'border-red-500 bg-red-500/10' : 'hover:border-[#444]' }}">
                <div class="text-[10px] uppercase tracking-wider text-red-400">Incidencias</div>
                <div class="font-display text-xl text-red-400 mt-1">{{ $counts['with_incidents'] }}</div>
            </a>
        </div>

        {{-- Barra de Filtros y Búsqueda --}}
        <form method="GET" action="{{ route('admin.editorial.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-4 rounded text-xs">
            {{-- Búsqueda --}}
            <div class="lg:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-[#8a8a8a] mb-1">Buscar por titular, asunto o remitente</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ $currentSearch }}" 
                        placeholder="Ej. Judas Priest, Sony Music, fe de erratas..."
                        class="lucille-product-field w-full text-xs pr-8"
                    >
                    @if ($currentSearch)
                        <a href="{{ route('admin.editorial.index') }}" class="absolute right-2 top-2 text-gray-400 hover:text-white">&times;</a>
                    @endif
                </div>
            </div>

            {{-- Filtro Estado --}}
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-[#8a8a8a] mb-1">Estado Editorial</label>
                <select name="status" class="lucille-product-field w-full text-xs" onchange="this.form.submit()">
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
                <label class="block text-[10px] uppercase tracking-wider text-[#8a8a8a] mb-1">Seguridad & Embargo</label>
                <select name="security" class="lucille-product-field w-full text-xs" onchange="this.form.submit()">
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
                    <label class="block text-[10px] uppercase tracking-wider text-[#8a8a8a] mb-1">Ordenar por</label>
                    <select name="sort" class="lucille-product-field w-full text-xs" onchange="this.form.submit()">
                        <option value="newest" {{ $currentSort === 'newest' ? 'selected' : '' }}>Más reciente</option>
                        <option value="oldest" {{ $currentSort === 'oldest' ? 'selected' : '' }}>Más antiguo</option>
                        <option value="embargo" {{ $currentSort === 'embargo' ? 'selected' : '' }}>Próximo Embargo</option>
                        <option value="urgent" {{ $currentSort === 'urgent' ? 'selected' : '' }}>Mayor Urgencia</option>
                    </select>
                </div>
                <button type="submit" class="lucille-button px-3 py-2 text-xs">Filtrar</button>
            </div>
        </form>

        {{-- Barra de Acciones Masivas (Aparece reactivamente con Alpine cuando selectedPosts > 0) --}}
        <div 
            x-show="selectedPosts.length > 0" 
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="sticky top-4 z-30 border border-lucille-accent bg-[rgba(16,16,18,.98)] shadow-[0_8px_30px_rgba(0,0,0,.7)] p-4 rounded flex flex-wrap items-center justify-between gap-3"
        >
            <div class="flex items-center gap-3">
                <span class="px-2.5 py-1 bg-lucille-accent text-white font-display text-sm font-bold rounded">
                    <span x-text="selectedPosts.length"></span> SELECCIONADOS
                </span>
                <span class="text-xs text-[#9a9a9a] hidden sm:inline">Acción coordinada elemento a elemento con validación de seguridad aislada</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Botón Aprobar Masivo --}}
                <form action="{{ route('admin.editorial.bulk-action') }}" method="POST" @submit="isSubmitting = true">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <template x-for="id in selectedPosts" :key="id">
                        <input type="hidden" name="post_ids[]" :value="id">
                    </template>
                    <button 
                        type="submit" 
                        :disabled="isSubmitting"
                        class="px-3 py-1.5 bg-[#1e4d2b] border border-[#2d7a44] text-[#b8e6c3] hover:text-white rounded text-xs font-semibold uppercase tracking-wider transition-colors disabled:opacity-50"
                        onclick="return confirm('¿Aprobar y publicar las noticias seleccionadas que cumplan los requisitos de seguridad y no tengan embargos ni incidencias pendientes?')"
                    >
                        ✅ Publicar Seleccionadas
                    </button>
                </form>

                {{-- Botón Programar Masivo --}}
                <button 
                    type="button" 
                    @click="bulkScheduleOpen = true"
                    class="px-3 py-1.5 bg-[#b8860b]/20 border border-[#b8860b] text-[#fef08a] hover:text-white rounded text-xs font-semibold uppercase tracking-wider transition-colors"
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
                        class="px-3 py-1.5 bg-[#7a2b2b]/30 border border-[#7a2b2b] text-[#fca5a5] hover:text-white rounded text-xs font-semibold uppercase tracking-wider transition-colors disabled:opacity-50"
                        onclick="return confirm('¿Rechazar las publicaciones seleccionadas conservando trazabilidad?')"
                    >
                        ❌ Rechazar
                    </button>
                </form>

                <button 
                    type="button" 
                    @click="selectedPosts = []; document.querySelectorAll('.post-select-checkbox').forEach(c => c.checked = false); selectAllPage = false;"
                    class="px-2.5 py-1 text-xs text-[#8a8a8a] hover:text-white"
                >
                    Limpiar
                </button>
            </div>
        </div>

        {{-- Tabla Principal de Comunicados --}}
        <div class="overflow-x-auto border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] rounded">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-[#2b2b2b] text-[#dcdcdc] font-display uppercase tracking-wider bg-[rgba(0,0,0,.3)]">
                    <tr>
                        <th class="p-3 w-8 text-center">
                            <input 
                                type="checkbox" 
                                x-model="selectAllPage" 
                                @change="toggleAll($event)"
                                class="rounded border-[#333] bg-[#1a1a1c] text-lucille-accent focus:ring-lucille-accent cursor-pointer"
                                title="Seleccionar todos los elementos de esta página"
                            >
                        </th>
                        <th class="p-3 w-20">Portada</th>
                        <th class="p-3">Titular Propuesto & Resumen Editorial</th>
                        <th class="p-3 w-48">Agencia / Sello</th>
                        <th class="p-3 w-32">Multimedia</th>
                        <th class="p-3 w-36">Estado / Alertas</th>
                        <th class="p-3 w-36 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2b2b2b] text-[#9a9a9a]">
                    @forelse ($posts as $post)
                        @php
                            $meta = $post->sanitizedMeta;
                            $hasActiveEmbargo = $meta && $meta->is_embargo_active && $meta->embargo_until?->isFuture();
                            $pendingIncident = $post->incidents->first();
                            $mediaInv = app(\App\Services\EmailMediaInventoryExtractor::class)->getEffectiveInventory($meta?->media_inventory, is_array($post->content) ? json_encode($post->content) : (string) $post->content);
                        @endphp
                        <tr class="hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            {{-- Checkbox individual --}}
                            <td class="p-3 text-center align-middle">
                                <input 
                                    type="checkbox" 
                                    value="{{ $post->id }}" 
                                    class="post-select-checkbox rounded border-[#333] bg-[#1a1a1c] text-lucille-accent focus:ring-lucille-accent cursor-pointer"
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
                            <td class="p-3 align-top">
                                @if ($post->featured_image_url)
                                    <img src="{{ $post->featured_image_url }}" alt="Portada" class="w-16 h-16 object-cover rounded border border-[#2b2b2b] bg-black">
                                @else
                                    <div class="w-16 h-16 rounded border border-[#2b2b2b] bg-[#141416] flex items-center justify-center text-[#555] text-xs font-mono">
                                        Sin Portada
                                    </div>
                                @endif
                            </td>

                            {{-- Titular y Resumen --}}
                            <td class="p-3 align-top">
                                <div class="flex items-center gap-2">
                                    <span class="font-display text-sm uppercase tracking-wider text-[#f2f2f2] font-semibold hover:text-lucille-accent transition-colors cursor-pointer" @click="openQuickView({{ $post->id }})">
                                        {{ $post->title }}
                                    </span>
                                </div>
                                <p class="mt-1 text-[#a0a0a0] line-clamp-2 text-[11px] leading-relaxed">
                                    {{ $post->excerpt ?: Str::limit(strip_tags(is_array($post->content) ? json_encode($post->content) : $post->content), 140) }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-[#777]">
                                    <span>Recibido: <strong class="text-gray-300" title="{{ $post->created_at->format('d/m/Y H:i:s') }}">{{ $post->created_at->diffForHumans() }}</strong></span>
                                    @if ($post->source_subject)
                                        <span>• Asunto Original: <em class="text-gray-400">"{{ Str::limit($post->source_subject, 40) }}"</em></span>
                                    @endif
                                </div>
                            </td>

                            {{-- Agencia / Remitente --}}
                            <td class="p-3 align-top">
                                @php
                                    $sourceInfo = app(\App\Services\EmailSenderSourceResolver::class)->resolve((string) ($post->author_email ?? $meta?->sender_email));
                                @endphp
                                <div class="font-semibold text-gray-200 text-xs">
                                    {{ $sourceInfo['source']?->name ?? 'Remitente No Registrado' }}
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5 truncate max-w-[180px]" title="{{ $post->author_email }}">
                                    {{ $post->author_email ?: 'Sin remitente' }}
                                </div>
                                <div class="mt-1.5 flex items-center gap-1.5">
                                    @if ($sourceInfo['status'] === 'authorized')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#1e4d2b] text-[#b8e6c3] border border-[#2d7a44]">
                                            🛡️ AUTORIZADA
                                        </span>
                                    @elseif ($sourceInfo['status'] === 'pending')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#b8860b]/20 text-[#fef08a] border border-[#b8860b]">
                                            ⏳ PENDIENTE
                                        </span>
                                    @elseif ($sourceInfo['status'] === 'blocked')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#7a2b2b]/30 text-[#fca5a5] border border-[#7a2b2b]">
                                            🚫 BLOQUEADA
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-white/5 text-gray-400 border border-white/10">
                                            ℹ️ NO REGISTRADA
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Contadores Multimedia Reales --}}
                            <td class="p-3 align-top">
                                <div class="space-y-1 text-[11px]">
                                    <div class="flex items-center justify-between" title="Fotografías adjuntas vs enlazadas">
                                        <span>📷 Fotos:</span>
                                        <strong class="text-gray-200">
                                            {{ $mediaInv['attached_photos_count'] !== null ? $mediaInv['attached_photos_count'] : 'N/D' }} adj / {{ $mediaInv['linked_photos_count'] }} enl
                                        </strong>
                                    </div>
                                    <div class="flex items-center justify-between" title="Vídeos enlazados">
                                        <span>📹 Vídeos:</span>
                                        <strong class="text-gray-200">{{ $mediaInv['linked_videos_count'] }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between" title="Audios MP3 adjuntos vs plataformas">
                                        <span>🎵 Audios:</span>
                                        <strong class="text-gray-200">
                                            {{ $mediaInv['attached_mp3s_count'] !== null ? $mediaInv['attached_mp3s_count'] : 'N/D' }} mp3 / {{ $mediaInv['linked_music_count'] }} ext
                                        </strong>
                                    </div>
                                    <div class="flex items-center justify-between" title="Documentos o dossiers">
                                        <span>📄 Docs:</span>
                                        <strong class="text-gray-200">
                                            {{ $mediaInv['attached_docs_count'] !== null ? $mediaInv['attached_docs_count'] : 'N/D' }}
                                        </strong>
                                    </div>
                                </div>
                            </td>

                            {{-- Estado y Alertas --}}
                            <td class="p-3 align-top space-y-1.5">
                                {{-- Estado Principal --}}
                                <div>
                                    @if ($post->status === 'published' || $post->is_published)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#1e4d2b] text-[#b8e6c3] border border-[#2d7a44]">
                                            PUBLICADO
                                        </span>
                                    @elseif ($post->status === 'scheduled')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#1e3a5f] text-[#93c5fd] border border-[#2563eb]">
                                            ⏳ PROGRAMADO ({{ $post->published_at?->format('d/m H:i') }})
                                        </span>
                                    @elseif ($post->status === 'rejected')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#3b1212] text-[#fca5a5] border border-[#7a2b2b]">
                                            RECHAZADO
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#262626] text-gray-300 border border-[#444]">
                                            BORRADOR
                                        </span>
                                    @endif
                                </div>

                                {{-- Alerta de Embargo --}}
                                @if ($hasActiveEmbargo)
                                    <div class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-300 bg-amber-950/40 border border-amber-600/50 px-1.5 py-0.5 rounded" title="Material bajo embargo de prensa">
                                        <span>⏳ EMBARGO:</span>
                                        <span>{{ $meta->embargo_until->format('d/m H:i') }}</span>
                                    </div>
                                @endif

                                {{-- Alerta de Incidencia --}}
                                @if ($pendingIncident)
                                    <div class="inline-flex items-center gap-1 text-[10px] font-bold text-red-300 bg-red-950/40 border border-red-600/50 px-1.5 py-0.5 rounded animate-pulse" title="Rectificación o conflicto pendiente">
                                        <span>🚨 INCIDENCIA ({{ ucfirst($pendingIncident->priority) }})</span>
                                    </div>
                                @endif

                                {{-- Alerta de Materiales Privados / EPK --}}
                                @if (!empty($meta?->redacted_links))
                                    <div class="text-[9px] text-[#fca5a5]">
                                        🔒 {{ count($meta->redacted_links) }} enlace(s) EPK redactado(s)
                                    </div>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="p-3 align-top text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Vista Rápida --}}
                                    <button 
                                        type="button" 
                                        @click="openQuickView({{ $post->id }})"
                                        class="p-1.5 rounded border border-[#333] bg-[#1a1a1c] text-gray-300 hover:text-white hover:border-[#555] transition-colors"
                                        title="Vista Rápida"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>

                                    {{-- Editar en Editor por Bloques --}}
                                    <a 
                                        href="{{ route('admin.posts.edit', $post) }}" 
                                        class="p-1.5 rounded border border-[#333] bg-[#1a1a1c] text-gray-300 hover:text-white hover:border-[#555] transition-colors"
                                        title="Editar Noticia Completa"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </a>

                                    {{-- Aprobar y Publicar Ahora --}}
                                    @if (!$post->is_published && $post->status !== 'published')
                                        <form action="{{ route('admin.editorial.approve', $post) }}" method="POST" class="inline" onsubmit="return confirm('¿Aprobar y publicar inmediatamente esta noticia?')">
                                            @csrf
                                            <button 
                                                type="submit" 
                                                class="p-1.5 rounded border border-[#1e4d2b] bg-[#1e4d2b]/40 text-[#4ade80] hover:bg-[#1e4d2b] transition-colors"
                                                title="Aprobar y Publicar Ahora"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        </form>

                                        {{-- Programar Publicación --}}
                                        <button 
                                            type="button" 
                                            @click="openSchedule({{ $post->id }}, '{{ $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '' }}')"
                                            class="p-1.5 rounded border border-[#b8860b]/40 bg-[#b8860b]/20 text-[#fef08a] hover:bg-[#b8860b]/40 transition-colors"
                                            title="Programar Fecha de Publicación"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </button>
                                    @endif

                                    {{-- Rechazar --}}
                                    @if ($post->status !== 'rejected')
                                        <button 
                                            type="button" 
                                            @click="openReject({{ $post->id }})"
                                            class="p-1.5 rounded border border-[#7a2b2b]/40 bg-[#7a2b2b]/20 text-[#fca5a5] hover:bg-[#7a2b2b]/40 transition-colors"
                                            title="Rechazar Noticia"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-[#777]">
                                No se encontraron comunicados con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($posts->hasPages())
            <div class="mt-4">
                {{ $posts->links() }}
            </div>
        @endif

        {{-- DRAWER MODAL: VISTA RÁPIDA EDITORIAL SEGURA --}}
        <div 
            x-show="quickViewOpen" 
            x-cloak
            class="fixed inset-0 z-50 overflow-hidden" 
            aria-labelledby="modal-quickview-title" 
            role="dialog" 
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm transition-opacity" @click="quickViewOpen = false"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div 
                    x-show="quickViewOpen"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-300"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="w-screen max-w-2xl border-l border-[#2b2b2b] bg-[#101012] text-[#dcdcdc] p-6 overflow-y-auto space-y-6 shadow-2xl"
                >
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-4">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-lucille-accent">Inspección Editorial Segura</span>
                            <h2 id="modal-quickview-title" class="font-display text-xl uppercase tracking-wider text-white mt-0.5" x-text="quickViewData ? quickViewData.title : 'Cargando...'"></h2>
                        </div>
                        <button type="button" @click="quickViewOpen = false" class="text-gray-400 hover:text-white text-2xl">&times;</button>
                    </div>

                    {{-- Estado de Carga --}}
                    <template x-if="quickViewLoading">
                        <div class="py-16 text-center text-gray-400">
                            <div class="animate-spin inline-block w-8 h-8 border-2 border-current border-t-transparent text-lucille-accent rounded-full" role="status"></div>
                            <p class="mt-2 text-xs">Cargando datos saneados desde el servidor...</p>
                        </div>
                    </template>

                    {{-- Contenido cuando ya cargó --}}
                    <template x-if="!quickViewLoading && quickViewData">
                        <div class="space-y-6">
                            {{-- Pestañas de Vista Rápida --}}
                            <div class="flex gap-2 border-b border-[#2b2b2b] text-xs font-semibold uppercase tracking-wider">
                                <button type="button" @click="quickViewTab = 'summary'" :class="quickViewTab === 'summary' ? 'text-white border-b-2 border-lucille-accent' : 'text-[#888] hover:text-white'" class="pb-2">
                                    📋 Resumen IA
                                </button>
                                <button type="button" @click="quickViewTab = 'content'" :class="quickViewTab === 'content' ? 'text-white border-b-2 border-lucille-accent' : 'text-[#888] hover:text-white'" class="pb-2">
                                    📰 Noticia Maquetada
                                </button>
                                <button type="button" @click="quickViewTab = 'media'" :class="quickViewTab === 'media' ? 'text-white border-b-2 border-lucille-accent' : 'text-[#888] hover:text-white'" class="pb-2">
                                    🎵 Multimedia & Enlaces
                                </button>
                                <button type="button" @click="quickViewTab = 'security'" :class="quickViewTab === 'security' ? 'text-white border-b-2 border-lucille-accent' : 'text-[#888] hover:text-white'" class="pb-2">
                                    🛡️ Auditoría & IA
                                </button>
                            </div>

                            {{-- Pestaña 1: Resumen IA --}}
                            <div x-show="quickViewTab === 'summary'" class="space-y-4">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4 rounded text-xs space-y-2">
                                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">Titular Propuesto</div>
                                    <div class="font-display text-base text-white" x-text="quickViewData.title"></div>
                                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-bold pt-2">Resumen Periodístico (Lead)</div>
                                    <p class="text-gray-300 leading-relaxed" x-text="quickViewData.excerpt"></p>
                                </div>

                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="border border-[#2b2b2b] p-3 rounded bg-[#141416]">
                                        <div class="text-[10px] uppercase text-gray-400 font-bold">Estado Actual</div>
                                        <div class="font-bold text-white mt-1 uppercase tracking-wider" x-text="quickViewData.status"></div>
                                    </div>
                                    <div class="border border-[#2b2b2b] p-3 rounded bg-[#141416]">
                                        <div class="text-[10px] uppercase text-gray-400 font-bold">Fecha Recepción</div>
                                        <div class="text-gray-300 mt-1" x-text="quickViewData.created_at"></div>
                                    </div>
                                </div>

                                <template x-if="quickViewData.meta.editorial_notes">
                                    <div class="border border-blue-900/50 bg-blue-950/20 p-3 rounded text-xs">
                                        <div class="text-[10px] uppercase font-bold text-blue-400">Notas para Editores (Extraídas de Prensa)</div>
                                        <div class="text-gray-300 mt-1" x-text="quickViewData.meta.editorial_notes"></div>
                                    </div>
                                </template>
                            </div>

                            {{-- Pestaña 2: Noticia Maquetada --}}
                            <div x-show="quickViewTab === 'content'" class="space-y-4 text-xs">
                                <template x-if="quickViewData.featured_image">
                                    <div class="border border-[#2b2b2b] rounded overflow-hidden">
                                        <img :src="quickViewData.featured_image" class="w-full h-48 object-cover">
                                    </div>
                                </template>
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4 rounded space-y-3">
                                    <div class="text-[10px] uppercase text-gray-400 font-bold">Vista Previa de Redacción Editorial</div>
                                    <div class="text-gray-300 leading-relaxed whitespace-pre-line" x-text="quickViewData.content_preview"></div>
                                </div>
                            </div>

                            {{-- Pestaña 3: Multimedia & Enlaces --}}
                            <div x-show="quickViewTab === 'media'" class="space-y-4 text-xs">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="border border-[#2b2b2b] p-3 rounded bg-[#141416]">
                                        <div class="text-[10px] uppercase text-gray-400 font-bold">Fotografías</div>
                                        <div class="text-white mt-1" x-text="(quickViewData.media_inventory.attached_photos_count !== null ? quickViewData.media_inventory.attached_photos_count : 'N/D') + ' adjuntas / ' + quickViewData.media_inventory.linked_photos_count + ' enlazadas'"></div>
                                    </div>
                                    <div class="border border-[#2b2b2b] p-3 rounded bg-[#141416]">
                                        <div class="text-[10px] uppercase text-gray-400 font-bold">Audio / MP3</div>
                                        <div class="text-white mt-1" x-text="(quickViewData.media_inventory.attached_mp3s_count !== null ? quickViewData.media_inventory.attached_mp3s_count : 'N/D') + ' MP3 adjuntos / ' + quickViewData.media_inventory.linked_music_count + ' enlaces'"></div>
                                    </div>
                                </div>

                                {{-- Enlaces Públicos Permitidos --}}
                                <template x-if="quickViewData.media_inventory.public_video_links && quickViewData.media_inventory.public_video_links.length > 0">
                                    <div class="border border-[#2b2b2b] p-3 rounded space-y-2">
                                        <div class="text-[10px] uppercase font-bold text-gray-400">Vídeos Públicos Verificados</div>
                                        <template x-for="v in quickViewData.media_inventory.public_video_links" :key="v.url">
                                            <div class="flex items-center justify-between text-xs bg-white/5 p-2 rounded">
                                                <span class="text-gray-300" x-text="v.label"></span>
                                                <a :href="v.url" target="_blank" class="text-lucille-accent underline text-[11px]">Ver en plataforma</a>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Enlaces Privados / EPK Redactados --}}
                                <template x-if="quickViewData.meta.redacted_materials && quickViewData.meta.redacted_materials.length > 0">
                                    <div class="border border-red-900/40 bg-red-950/20 p-3 rounded space-y-2">
                                        <div class="text-[10px] uppercase font-bold text-red-400">Material Reservado de Prensa (Protegido)</div>
                                        <template x-for="rm in quickViewData.meta.redacted_materials" :key="rm.platform">
                                            <div class="text-xs text-red-200">
                                                🔒 <strong x-text="rm.platform"></strong>: <span x-text="rm.note"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            {{-- Pestaña 4: Auditoría & Telemetría IA --}}
                            <div x-show="quickViewTab === 'security'" class="space-y-4 text-xs">
                                <div class="border border-[#2b2b2b] p-4 rounded bg-[#141416] space-y-2">
                                    <div class="text-[10px] uppercase text-gray-400 font-bold">Verificación del Remitente</div>
                                    <div>Agencia: <strong class="text-white" x-text="quickViewData.source.name"></strong></div>
                                    <div>Correo: <span class="text-gray-300" x-text="quickViewData.author_email"></span></div>
                                    <div>Estado Fuente: <span class="font-bold uppercase tracking-wider" x-text="quickViewData.source.status"></span></div>
                                </div>

                                <template x-if="quickViewData.ai_telemetry">
                                    <div class="border border-[#2b2b2b] p-4 rounded bg-[#141416] space-y-2">
                                        <div class="text-[10px] uppercase text-gray-400 font-bold">Telemetría de Inteligencia Artificial</div>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div>Modelo: <strong class="text-white" x-text="quickViewData.ai_telemetry.model"></strong></div>
                                            <div>Duración: <strong class="text-white" x-text="quickViewData.ai_telemetry.duration_ms + ' ms'"></strong></div>
                                            <div>Tokens Entrada: <strong class="text-white" x-text="quickViewData.ai_telemetry.prompt_tokens"></strong></div>
                                            <div>Tokens Salida: <strong class="text-white" x-text="quickViewData.ai_telemetry.completion_tokens"></strong></div>
                                            <div>Coste Estimado: <strong class="text-white" x-text="quickViewData.ai_telemetry.cost_eur + ' €'"></strong></div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Botones de Acción al pie del Drawer --}}
                            <div class="border-t border-[#2b2b2b] pt-4 flex flex-wrap items-center justify-between gap-3">
                                <a :href="'/admin/posts/' + quickViewData.id + '/edit'" class="lucille-button text-xs">
                                    ✏️ Abrir Editor Completo
                                </a>

                                <div class="flex items-center gap-2">
                                    <form :action="'/admin/editorial/' + quickViewData.id + '/approve'" method="POST" onsubmit="return confirm('¿Aprobar y publicar ahora?')">
                                        @csrf
                                        <button type="submit" class="lucille-button-solid text-xs bg-[#1e4d2b] border-[#2d7a44] text-[#b8e6c3] hover:text-white">
                                            ✅ Publicar Ahora
                                        </button>
                                    </form>

                                    <button type="button" @click="quickViewOpen = false; openSchedule(quickViewData.id)" class="lucille-button text-xs">
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
            <div class="w-full max-w-md border border-[#2b2b2b] bg-[#101012] p-6 rounded shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Programar Publicación</h3>
                <p class="text-xs text-[#9a9a9a]">Selecciona la fecha y hora futura para publicar esta noticia. El cron de Seven Rock Radio publicará el artículo automáticamente cuando expire el plazo.</p>

                <form :action="'/admin/editorial/' + scheduleTargetId + '/schedule'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Fecha y Hora de Publicación</label>
                        <input type="datetime-local" name="scheduled_at" x-model="scheduleDate" required class="lucille-product-field w-full text-xs">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="scheduleModalOpen = false" class="lucille-button text-xs">Cancelar</button>
                        <button type="submit" class="lucille-button-solid text-xs bg-[#1e3a5f] border-[#2563eb] text-[#93c5fd]">Confirmar Programación</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL INDIVIDUAL: RECHAZAR NOTICIA --}}
        <div x-show="rejectModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-md border border-[#2b2b2b] bg-[#101012] p-6 rounded shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Rechazar Noticia</h3>
                <p class="text-xs text-[#9a9a9a]">La noticia cambiará a estado rechazado sin eliminarse de la base de datos, garantizando trazabilidad y evitando que se re-procese el correo.</p>

                <form :action="'/admin/editorial/' + rejectTargetId + '/reject'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Motivo del Rechazo</label>
                        <input type="text" name="reason" x-model="rejectReason" placeholder="Ej. Fuera de línea editorial, spam, información obsoleta..." required class="lucille-product-field w-full text-xs">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="rejectModalOpen = false" class="lucille-button text-xs">Cancelar</button>
                        <button type="submit" class="lucille-button-solid text-xs bg-[#7a2b2b] border-[#991b1b] text-[#fca5a5]">Confirmar Rechazo</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL MASIVO: PROGRAMAR PUBLICACIÓN SELECCIONADAS --}}
        <div x-show="bulkScheduleOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-md border border-[#2b2b2b] bg-[#101012] p-6 rounded shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Programar Selección Masiva</h3>
                <p class="text-xs text-[#9a9a9a]">Se programarán <strong x-text="selectedPosts.length"></strong> noticias para la fecha especificada. Las que tengan embargos posteriores a esta fecha permanecerán retenidas.</p>

                <form action="{{ route('admin.editorial.bulk-action') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="action" value="schedule">
                    <template x-for="id in selectedPosts" :key="id">
                        <input type="hidden" name="post_ids[]" :value="id">
                    </template>

                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-400 mb-1">Fecha y Hora Programada</label>
                        <input type="datetime-local" name="scheduled_at" x-model="bulkScheduleDate" required class="lucille-product-field w-full text-xs">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="bulkScheduleOpen = false" class="lucille-button text-xs">Cancelar</button>
                        <button type="submit" class="lucille-button-solid text-xs bg-[#1e3a5f] border-[#2563eb] text-[#93c5fd]">Programar Todas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
