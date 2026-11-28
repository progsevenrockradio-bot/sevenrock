<x-layouts.admin :title="'Nuevos Lanzamientos - '.$themeSettings->site_name">
    <div x-data="{ intakeOpen: @js((bool) request()->boolean('intake_panel')), rejectModalOpen: false, rejectAction: '', rejectTitle: '' }">
        @if (session('status') || session('success'))
            <div class="mb-5 flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                <span>{{ session('status') ?? session('success') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
            </div>
        @endif

        @if (session('error') || $errors->any())
            <div class="mb-5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                @if (session('error'))
                    <div>{{ session('error') }}</div>
                @endif
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        {{-- Cabecera --}}
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Bandeja de Nuevos Lanzamientos</h1>
                <p class="mt-1 text-sm text-gray-400">Revisión unificada de propuestas por correo, maquetas de /submissions y altas manuales.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    @click="intakeOpen = !intakeOpen"
                    class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg border {{ $intakeSettings['enabled'] ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-200' : 'border-white/10 bg-white/5 text-gray-200 hover:bg-white/10' }} text-xs font-semibold transition-all"
                >
                    <span class="h-2 w-2 rounded-full {{ $intakeSettings['enabled'] ? 'bg-emerald-400' : 'bg-gray-500' }}"></span>
                    <span>Recepción por correo ({{ $intakeSettings['enabled'] ? 'Activa' : 'Desactivada' }})</span>
                </button>
                <a href="{{ route('admin.new-releases.create') }}" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide transition-all">
                    <span>+ Nuevo Lanzamiento</span>
                </a>
            </div>
        </div>

        {{-- Panel plegable: Configuración independiente de recepción por correo (Piloto) --}}
        <section x-show="intakeOpen" x-cloak class="mb-6 rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5 shadow-xl">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 pb-3">
                <div>
                    <h2 class="font-display text-lg uppercase tracking-wider text-white">Recepción por correo de lanzamientos (Piloto independiente)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Modo estrictamente solo lectura (FT_PEEK). No modifica banderas, no descarga adjuntos ni publica automáticamente. Separado de DV-1 y de programas externos.</p>
                </div>
                <button type="button" @click="intakeOpen = false" class="text-xs text-gray-400 hover:text-white">Cerrar ✕</button>
            </div>

            <form action="{{ route('admin.new-releases.intake.settings') }}" method="POST" class="grid gap-4 md:grid-cols-4 items-end">
                @csrf
                <div>
                    <label class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Buzón receptor</label>
                    <input type="email" name="mailbox_email" value="{{ old('mailbox_email', $intakeSettings['mailbox_email']) }}" placeholder="lanzamientos@ejemplo.com" class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Fecha inicio de consulta</label>
                    <input type="date" name="since_date" value="{{ old('since_date', $intakeSettings['since_date']) }}" class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100">
                </div>
                <div class="space-y-1.5 text-xs text-gray-300">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="enabled" value="1" @checked($intakeSettings['enabled']) class="rounded border-white/20 bg-black/50 text-[#c32720]">
                        <span>Activar recepción piloto</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-gray-400">
                        <input type="checkbox" name="use_external_ai" value="1" @checked($intakeSettings['use_external_ai']) class="rounded border-white/20 bg-black/50 text-[#c32720]">
                        <span>Usar IA externa (desactivado por defecto)</span>
                    </label>
                </div>
                <div>
                    <button type="submit" class="w-full h-9 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold">Guardar configuración</button>
                </div>
            </form>

            {{-- Remitentes autorizados --}}
            <div class="border-t border-white/10 pt-4 space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-300">Remitentes autorizados ({{ $intakeSenders->count() }})</h3>
                <form action="{{ route('admin.new-releases.intake.senders.store') }}" method="POST" class="grid gap-3 md:grid-cols-5 items-end">
                    @csrf
                    <div>
                        <label class="block text-[10px] uppercase text-gray-400 mb-1">Correo remitente</label>
                        <input type="email" name="email" required placeholder="prensa@sello.com" class="w-full h-8 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase text-gray-400 mb-1">Artista, sello o agencia</label>
                        <input type="text" name="entity_name" required placeholder="Ej. Ripple Music" class="w-full h-8 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase text-gray-400 mb-1">Tipo</label>
                        <select name="entity_type" class="w-full h-8 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100">
                            @foreach ($entityTypes as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase text-gray-400 mb-1">Notas (opcional)</label>
                        <input type="text" name="notes" placeholder="Piloto inicial" class="w-full h-8 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100">
                    </div>
                    <div>
                        <button type="submit" class="w-full h-8 rounded-lg border border-white/15 bg-white/10 hover:bg-white/15 text-xs font-semibold text-white">+ Añadir remitente</button>
                    </div>
                </form>

                @if ($intakeSenders->isNotEmpty())
                    <div class="overflow-x-auto rounded-lg border border-white/10">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/[0.03] text-[10px] uppercase text-gray-400">
                                <tr>
                                    <th class="px-3 py-2">Remitente</th>
                                    <th class="px-3 py-2">Entidad</th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-gray-300">
                                @foreach ($intakeSenders as $senderItem)
                                    <tr>
                                        <td class="px-3 py-2 font-mono">{{ $senderItem->email }}</td>
                                        <td class="px-3 py-2">{{ $senderItem->entity_name }} <span class="text-gray-500">({{ $senderItem->entityTypeLabel() }})</span></td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] {{ $senderItem->is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-white/5 text-gray-400' }}">
                                                {{ $senderItem->is_active ? 'Activo' : 'Pausado' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right space-x-2">
                                            <form action="{{ route('admin.new-releases.intake.senders.toggle', $senderItem) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-sky-300 hover:underline">{{ $senderItem->is_active ? 'Pausar' : 'Activar' }}</button>
                                            </form>
                                            <form action="{{ route('admin.new-releases.intake.senders.destroy', $senderItem) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-rose-300 hover:underline">Quitar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        {{-- Pestañas de bandeja --}}
        <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-white/10 pb-3">
            @foreach ([
                'pending_review' => ['label' => 'Por revisar', 'tone' => 'amber'],
                'published' => ['label' => 'Publicados', 'tone' => 'emerald'],
                'rejected' => ['label' => 'Rechazados', 'tone' => 'rose'],
            ] as $tabKey => $tabMeta)
                <a
                    href="{{ route('admin.new-releases.index', array_merge(request()->except('page'), ['tab' => $tabKey])) }}"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors {{ $tab === $tabKey ? 'bg-[#c32720] text-white' : 'border border-white/10 bg-[#101012] text-gray-300 hover:bg-white/5' }}"
                >
                    <span>{{ $tabMeta['label'] }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] {{ $tab === $tabKey ? 'bg-black/30 text-white' : 'bg-white/10 text-gray-300' }}">
                        {{ $counts[$tabKey] ?? 0 }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Buscador y filtros --}}
        <form method="GET" action="{{ route('admin.new-releases.index') }}" class="mb-5 rounded-xl border border-white/10 bg-[#101012] p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1">Buscar</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Título, artista, Message-ID..." class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1">Artista</label>
                <input type="text" name="artist" value="{{ $filters['artist'] }}" placeholder="Nombre banda/artista" class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1">Remitente</label>
                <input type="text" name="sender" value="{{ $filters['sender'] }}" placeholder="correo@sello.com" class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1">Tipo</label>
                <select name="release_type" class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100">
                    <option value="">Todos</option>
                    @foreach ($releaseTypes as $typeKey => $typeLabel)
                        <option value="{{ $typeKey }}" @selected($filters['release_type'] === $typeKey)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-1.5">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1">Desde</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2 text-xs text-gray-100">
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1">Hasta</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2 text-xs text-gray-100">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 h-9 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold">Filtrar</button>
                <a href="{{ route('admin.new-releases.index', ['tab' => $tab]) }}" class="h-9 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-xs text-gray-300 inline-flex items-center">Limpiar</a>
            </div>
        </form>

        {{-- Tabla de registros --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-white/10 bg-white/[0.02] text-[11px] font-medium uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="px-4 py-3.5 w-14">Portada</th>
                            <th class="px-4 py-3.5">Lanzamiento y origen</th>
                            <th class="px-4 py-3.5">Fechas</th>
                            <th class="px-4 py-3.5">Estado y avisos</th>
                            <th class="px-4 py-3.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-gray-300">
                        @forelse ($newReleases as $release)
                            @php
                                $missing = $release->missingFieldsSummary();
                                $senderEmail = $release->source_sender_email ?: $release->author_email;
                            @endphp
                            <tr class="hover:bg-white/[0.02] transition-colors align-top">
                                <td class="px-4 py-3.5">
                                    @if ($release->cover_image)
                                        <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="h-11 w-11 rounded-lg object-cover border border-white/10 bg-black">
                                    @else
                                        <div class="h-11 w-11 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-[10px] text-gray-500 font-mono" title="Sin portada registrada">
                                            Sin img
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-medium text-white text-sm">{{ $release->title }}</div>
                                    <div class="text-xs text-gray-300">{{ $release->artist_name }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">
                                        <span class="rounded bg-white/10 px-1.5 py-0.5 uppercase text-gray-300">{{ $release->releaseTypeLabel() }}</span>
                                        <span class="rounded bg-white/5 px-1.5 py-0.5 text-gray-400">Origen: {{ $release->origin ?? 'unknown' }}</span>
                                        @if ($senderEmail)
                                            <span class="font-mono text-gray-400">{{ $senderEmail }}</span>
                                        @endif
                                    </div>
                                    @if ($release->rejection_reason && $tab === 'rejected')
                                        <div class="mt-1.5 rounded border border-rose-500/25 bg-rose-500/10 px-2 py-1 text-[11px] text-rose-200">
                                            <strong>Motivo:</strong> {{ $release->rejection_reason }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-xs space-y-0.5 whitespace-nowrap">
                                    @if ($release->announced_at)
                                        <div><span class="text-gray-500">Anunciado:</span> {{ $release->announced_at->format('d/m/Y') }}</div>
                                    @endif
                                    <div>
                                        <span class="text-gray-500">{{ $release->released_at?->isFuture() ? 'Previsto:' : 'Lanzamiento:' }}</span>
                                        <span class="{{ $release->released_at?->isFuture() ? 'text-amber-300 font-medium' : 'text-gray-200' }}">
                                            {{ $release->released_at ? $release->released_at->format('d/m/Y') : 'No registrada' }}
                                        </span>
                                    </div>
                                    @if ($release->published_at)
                                        <div><span class="text-gray-500">Publicado:</span> {{ $release->published_at->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-xs space-y-1">
                                    @if ($release->isUnderActiveEmbargo())
                                        <span class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/15 px-2 py-0.5 text-[10px] text-amber-200">
                                            ⏳ Embargo{{ $release->embargo_until ? ' hasta ' . $release->embargo_until->format('d/m/Y H:i') : ' indefinido' }}
                                        </span>
                                    @endif
                                    @if ($release->security_status === 'held_security')
                                        <span class="inline-flex items-center rounded-full border border-rose-500/40 bg-rose-500/15 px-2 py-0.5 text-[10px] text-rose-200">
                                            🔒 Retención de seguridad
                                        </span>
                                    @endif
                                    @if ($tab === 'pending_review' && count($missing) > 0)
                                        <div class="text-[11px] text-amber-300/90">
                                            Falta: {{ implode(', ', $missing) }}
                                        </div>
                                    @endif
                                    @if ($release->reviewer)
                                        <div class="text-[10px] text-gray-500">Revisado por {{ $release->reviewer->name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($tab === 'pending_review')
                                            <form action="{{ route('admin.new-releases.approve', $release) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="h-8 px-2.5 rounded-lg bg-emerald-600/80 hover:bg-emerald-600 text-white text-xs font-semibold transition-all">
                                                    Aprobar
                                                </button>
                                            </form>
                                            <button
                                                type="button"
                                                @click="rejectModalOpen = true; rejectAction = '{{ route('admin.new-releases.reject', $release) }}'; rejectTitle = @js($release->title)"
                                                class="h-8 px-2.5 rounded-lg border border-rose-500/30 bg-rose-500/10 hover:bg-rose-500/20 text-rose-200 text-xs font-medium"
                                            >
                                                Rechazar
                                            </button>
                                        @else
                                            <form action="{{ route('admin.new-releases.return-to-review', $release) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="h-8 px-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 text-amber-200 text-xs font-medium">
                                                    A revisión
                                                </button>
                                            </form>
                                        @endif

                                        @if ($tab === 'published' && $release->audio_path && $release->origin !== 'submission' && ! $release->isBroadcastDelivered() && $release->security_status !== 'approved_for_broadcast' && ! $release->hasSecurityHold() && ! $release->isUnderActiveEmbargo())
                                            <form action="{{ route('admin.new-releases.approve-broadcast', $release) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="h-8 px-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 text-xs font-medium">
                                                    Aprobar Emisión
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('admin.new-releases.edit', $release) }}" class="inline-flex items-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium">
                                            Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-500">No hay lanzamientos en esta bandeja con los filtros actuales.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($newReleases->hasPages())
            <div class="mt-4">{{ $newReleases->links() }}</div>
        @endif

        {{-- Modal de rechazo con motivo --}}
        <div x-show="rejectModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @keydown.escape.window="rejectModalOpen = false">
            <div class="w-full max-w-md rounded-xl border border-white/10 bg-[#141418] p-5 shadow-2xl" @click.outside="rejectModalOpen = false">
                <h3 class="font-display text-lg uppercase text-white">Rechazar lanzamiento</h3>
                <p class="mt-1 text-xs text-gray-400">El registro <strong class="text-gray-200" x-text="rejectTitle"></strong> se conservará en «Rechazados» con su trazabilidad.</p>
                <form :action="rejectAction" method="POST" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs uppercase text-gray-300 mb-1">Motivo del rechazo <span class="text-[#c32720]">*</span></label>
                        <textarea name="rejection_reason" rows="3" required minlength="4" placeholder="Ej. Sin autorización de portada / fuera de línea editorial / duplicado..." class="w-full rounded-lg border border-white/10 bg-black/50 px-3 py-2 text-xs text-gray-100"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="rejectModalOpen = false" class="h-9 px-3 rounded-lg border border-white/10 bg-white/5 text-xs text-gray-300">Cancelar</button>
                        <button type="submit" class="h-9 px-4 rounded-lg bg-rose-600 hover:bg-rose-700 text-xs font-semibold text-white">Confirmar rechazo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
