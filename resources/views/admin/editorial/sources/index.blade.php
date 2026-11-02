@php
    $theme = $themeSettings ?? \App\Models\ThemeSetting::current();
@endphp
<x-layouts.admin :title="'Fuentes y Agencias - ' . $theme->site_name">
    <div 
        x-data="{
            createModalOpen: false,
            editModalOpen: false,
            editSource: null,
            openEdit(source) {
                this.editSource = source;
                this.editModalOpen = true;
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
                    <span>Fuentes y Agencias de Prensa</span>
                </h1>
                <p class="mt-1 text-sm text-gray-400">Gobierno de remitentes autorizados, sellos discográficos y verificación criptográfica (SPF/DKIM).</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('admin.editorial.index') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                    ← Volver a Bandeja
                </a>
                <button type="button" @click="createModalOpen = true" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all focus:outline-none focus:ring-2 focus:ring-[#c32720]/50">
                    + Registrar Nueva Fuente
                </button>
            </div>
        </div>

        {{-- Aviso de Seguridad Institucional --}}
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-xs text-amber-200 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-amber-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <strong>Aviso de Seguridad Operativa:</strong> Autorizar una agencia o sello discográfico verifica su procedencia e identidad técnica, pero <strong>NO publica automáticamente</strong> sus comunicados. Por política de Seven Rock Radio, todos los correos entrantes producen exclusivamente borradores para revisión editorial.
            </div>
        </div>

        {{-- Pestañas de Estado --}}
        <div class="flex flex-wrap gap-2 text-xs font-medium">
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'all' ? 'bg-[#c32720] text-white font-semibold' : 'border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10' }}">
                Todas ({{ $counts['all'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'authorized']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'authorized' ? 'bg-emerald-600 text-white font-semibold' : 'border border-white/10 bg-white/5 text-emerald-400 hover:bg-white/10' }}">
                🛡️ Autorizadas ({{ $counts['authorized'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'pending']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'pending' ? 'bg-amber-600 text-white font-semibold' : 'border border-white/10 bg-white/5 text-amber-300 hover:bg-white/10' }}">
                ⏳ Pendientes ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'suspended']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'suspended' ? 'bg-zinc-700 text-white font-semibold' : 'border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10' }}">
                ⏸️ Suspendidas ({{ $counts['suspended'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'blocked']) }}" class="px-3 py-1.5 rounded-lg transition-colors {{ $currentStatus === 'blocked' ? 'bg-rose-900 text-rose-200 font-semibold' : 'border border-white/10 bg-white/5 text-rose-400 hover:bg-white/10' }}">
                🚫 Bloqueadas ({{ $counts['blocked'] }})
            </a>
        </div>

        {{-- Tabla de Fuentes --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="p-3.5 sm:px-5">Nombre de la Entidad</th>
                            <th class="p-3.5 w-32">Tipo</th>
                            <th class="p-3.5">Dominios & Correos Autorizados</th>
                            <th class="p-3.5 w-28">Estado</th>
                            <th class="p-3.5 w-24 text-center">Incidencias</th>
                            <th class="p-3.5 w-40 text-right sm:pr-5">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-gray-300">
                        @forelse ($sources as $source)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-3.5 sm:px-5 align-top font-semibold text-white">
                                    <div class="text-sm font-medium text-white">{{ $source->name }}</div>
                                    @if ($source->notes)
                                        <div class="text-xs text-gray-400 mt-0.5 line-clamp-1 italic">{{ $source->notes }}</div>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top uppercase text-[11px] text-gray-400">
                                    {{ $source->organization_type }}
                                </td>
                                <td class="p-3.5 align-top space-y-1">
                                    @if (!empty($source->verified_domains))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($source->verified_domains as $domain)
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-white/5 border border-white/10 text-gray-300">
                                                    @ {{ $domain }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if (!empty($source->authorized_emails))
                                        <div class="flex flex-wrap gap-1 text-[11px] text-gray-400">
                                            @foreach ($source->authorized_emails as $email)
                                                <span>{{ $email }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top">
                                    @if ($source->status === 'authorized')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                                            AUTORIZADA
                                        </span>
                                    @elseif ($source->status === 'pending')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-amber-500/20 bg-amber-500/10 text-amber-300">
                                            PENDIENTE
                                        </span>
                                    @elseif ($source->status === 'suspended')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-white/10 bg-white/5 text-gray-300">
                                            SUSPENDIDA
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-rose-500/20 bg-rose-500/10 text-rose-300">
                                            BLOQUEADA
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top text-center font-bold text-gray-300">
                                    {{ $source->incidents_count }}
                                </td>
                                <td class="p-3.5 sm:pr-5 align-top text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($source->status !== 'authorized')
                                            <form action="{{ route('admin.editorial.sources.update-status', $source) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="authorized">
                                                <button type="submit" class="h-7 px-2 text-[10px] font-medium rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20" title="Autorizar Fuente">
                                                    Autorizar
                                                </button>
                                            </form>
                                        @endif

                                        @if ($source->status !== 'suspended')
                                            <form action="{{ route('admin.editorial.sources.update-status', $source) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="suspended">
                                                <button type="submit" class="h-7 px-2 text-[10px] font-medium rounded-md border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10" title="Suspender Temporalmente">
                                                    Pausar
                                                </button>
                                            </form>
                                        @endif

                                        @if ($source->status !== 'blocked')
                                            <form action="{{ route('admin.editorial.sources.update-status', $source) }}" method="POST" class="inline" onsubmit="return confirm('¿Bloquear permanentemente esta fuente?')">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="blocked">
                                                <button type="submit" class="h-7 px-2 text-[10px] font-medium rounded-md border border-rose-900/40 bg-rose-950/20 text-rose-300 hover:bg-rose-900/30" title="Bloquear Remitente">
                                                    Bloquear
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-10 text-center text-gray-500">
                                    No se encontraron fuentes con el criterio seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($sources->hasPages())
            <div class="mt-4">
                {{ $sources->links() }}
            </div>
        @endif

        {{-- MODAL CREAR FUENTE --}}
        <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#101012] p-6 shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Registrar Nueva Fuente / Agencia</h3>

                <form action="{{ route('admin.editorial.sources.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Nombre de la Agencia o Sello</label>
                        <input type="text" name="name" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Century Media Records">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Tipo de Organización</label>
                            <select name="organization_type" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                                <option value="label">Sello Discográfico (Label)</option>
                                <option value="agency">Agencia de Comunicación</option>
                                <option value="promoter">Promotora de Conciertos</option>
                                <option value="artist">Banda / Artista Directo</option>
                                <option value="distributor">Distribuidora</option>
                                <option value="media">Medio Colaborador</option>
                            </select>
                        </div>
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Estado Inicial</label>
                            <select name="status" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                                <option value="authorized">Autorizada (Verificada)</option>
                                <option value="pending" selected>Pendiente de Verificación</option>
                                <option value="suspended">Suspendida</option>
                                <option value="blocked">Bloqueada</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Dominios Verificados (separados por coma)</label>
                        <input type="text" name="verified_domains" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="centurymedia.com, sonymusic.com">
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Correos Autorizados (separados por coma)</label>
                        <input type="text" name="authorized_emails" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="press@centurymedia.com, promo@centurymedia.com">
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Notas / Acuerdos Editoriales</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Detalles de contacto o acuerdos de cobertura..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-white/10">
                        <button type="button" @click="createModalOpen = false" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-gray-300 text-xs hover:bg-white/10">Cancelar</button>
                        <button type="submit" class="inline-flex items-center justify-center h-8 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold">Guardar Fuente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
