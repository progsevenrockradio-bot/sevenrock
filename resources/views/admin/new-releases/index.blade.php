<x-layouts.admin :title="'Nuevos Lanzamientos - '.$themeSettings->site_name">
    @if (session('status') || session('success'))
        <div class="mb-6 flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('status') ?? session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 flex items-center justify-between rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-400 hover:text-rose-200 text-xs">✕</button>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Nuevos Lanzamientos</h1>
            <p class="mt-1 text-sm text-gray-400">Novedades discográficas y estrenos musicales destacados en la página principal.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.new-releases.create') }}" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all focus:outline-none focus:ring-2 focus:ring-[#c32720]/50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nuevo Lanzamiento</span>
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-5 py-3.5 w-16">Portada</th>
                        <th class="px-5 py-3.5">Título</th>
                        <th class="px-5 py-3.5">Artista</th>
                        <th class="px-5 py-3.5">Artista de Radio</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Fecha Lanzamiento</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Estado</th>
                        <th class="px-5 py-3.5 text-right whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-gray-300">
                    @forelse ($newReleases as $release)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5">
                                @if($release->cover_image)
                                    <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="h-10 w-10 rounded-lg object-cover border border-white/10 bg-black">
                                @else
                                    <div class="h-10 w-10 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-[10px] text-gray-500 font-mono">
                                        N/D
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-medium text-white text-sm">
                                {{ $release->title }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-300">{{ $release->artist_name }}</td>
                            <td class="px-5 py-3.5 text-xs text-gray-400">{{ $release->radioArtist?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs text-gray-400">{{ $release->released_at ? $release->released_at->format('d M Y') : '—' }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if ($release->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20">Activo</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-white/5 px-2.5 py-0.5 text-xs font-medium text-gray-400 border border-white/10">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    @if ($release->audio_path && $release->origin !== 'submission')
                                        @if ($release->isBroadcastDelivered())
                                            <span class="inline-flex items-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-[11px] font-medium text-emerald-300" title="Entrega efectiva confirmada en RadioBOSS. Archive.org requiere autorización independiente.">
                                                📻 Entregado a RadioBOSS
                                            </span>
                                        @elseif ($release->security_status === 'approved_for_broadcast')
                                            <span class="inline-flex items-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-[11px] font-medium text-emerald-300" title="Autorización administrativa concedida; subida a RadioBOSS en cola. Archive.org requiere autorización independiente.">
                                                ⏳ Emisión en cola (RadioBOSS)
                                            </span>
                                        @elseif ($release->hasBroadcastDeliveryFailed())
                                            <span class="inline-flex items-center rounded-lg border border-rose-500/20 bg-rose-500/10 px-2 py-1 text-[11px] font-medium text-rose-300" title="{{ $release->incident_reason ?: 'La subida a RadioBOSS falló o se detuvo por colisión.' }}">
                                                ⚠️ Fallo RadioBOSS
                                            </span>
                                            <form
                                                action="{{ route('admin.new-releases.approve-broadcast', $release) }}"
                                                method="POST"
                                                class="inline"
                                                data-confirm="¿Reintentar el envío a RadioBOSS? No se sobrescribirá ningún archivo remoto existente y Archive.org seguirá desactivado."
                                                data-confirm-title="Reintentar envío a RadioBOSS"
                                                data-confirm-action="Reintentar"
                                                data-confirm-tone="primary"
                                            >
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-8 px-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 text-xs font-medium transition-all" title="{{ $release->incident_reason ?: 'Reintentar subida segura a RadioBOSS' }}">
                                                    Reintentar Emisión
                                                </button>
                                            </form>
                                        @elseif ($release->hasSecurityHold())
                                            <span class="inline-flex items-center rounded-lg border border-rose-500/20 bg-rose-500/10 px-2 py-1 text-[11px] font-medium text-rose-300" title="No se puede aprobar la emisión mientras exista una retención de seguridad ({{ $release->security_status }}{{ $release->incident_reason ? ': ' . $release->incident_reason : '' }}).">
                                                🔒 Retención ({{ $release->security_status }})
                                            </span>
                                        @elseif ($release->isUnderActiveEmbargo())
                                            <span class="inline-flex items-center rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-[11px] font-medium text-gray-400" title="No se puede aprobar la emisión mientras el embargo siga vigente{{ $release->embargo_until ? ' (hasta ' . $release->embargo_until->format('d/m/Y H:i') . ')' : ' (embargo indefinido)' }}.">
                                                ⏳ Bajo embargo{{ $release->embargo_until ? ' (' . $release->embargo_until->format('d/m H:i') . ')' : '' }}
                                            </span>
                                        @else
                                            <form
                                                action="{{ route('admin.new-releases.approve-broadcast', $release) }}"
                                                method="POST"
                                                class="inline"
                                                data-confirm="¿Aprobar la emisión en radio? Solo se encolará la subida a RadioBOSS. No se publicará en Archive.org ni cambiará la visibilidad web."
                                                data-confirm-title="Aprobar emisión"
                                                data-confirm-action="Aprobar"
                                                data-confirm-tone="primary"
                                            >
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-8 px-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 text-xs font-medium transition-all" title="Aprobar para emisión en radio">
                                                    Aprobar Emisión
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    <a href="{{ route('admin.new-releases.edit', $release) }}" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                                        Editar
                                    </a>
                                    <form
                                        action="{{ route('admin.new-releases.destroy', $release) }}"
                                        method="POST"
                                        class="inline"
                                        data-confirm="¿Eliminar este lanzamiento definitivamente?"
                                        data-confirm-title="Eliminar lanzamiento"
                                        data-confirm-action="Eliminar"
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-rose-900/40 bg-rose-950/20 hover:bg-rose-900/30 text-rose-300 text-xs font-medium transition-all">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-500">No hay lanzamientos creados aún.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
