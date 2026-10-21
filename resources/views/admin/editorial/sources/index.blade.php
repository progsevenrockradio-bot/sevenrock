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
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.25)] p-4 text-sm text-[#b8e6c3] flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-[#b8e6c3] hover:text-white">&times;</button>
            </div>
        @endif

        {{-- Cabecera --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-[#2b2b2b] pb-4">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc] flex items-center gap-3">
                    <span>Fuentes y Agencias de Prensa</span>
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-[#9a9a9a]">Gobierno de remitentes autorizados, sellos discográficos y verificación criptográfica (SPF/DKIM).</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wider">
                <a href="{{ route('admin.editorial.index') }}" class="px-3 py-2 rounded border border-[#2b2b2b] bg-[#1a1a1c] text-[#9a9a9a] hover:text-white transition-colors">
                    ← Volver a Bandeja
                </a>
                <button type="button" @click="createModalOpen = true" class="lucille-button-solid text-xs">
                    + Registrar Nueva Fuente
                </button>
            </div>
        </div>

        {{-- Aviso de Seguridad Institucional --}}
        <div class="border border-[#b8860b]/40 bg-[#b8860b]/10 p-4 rounded text-xs text-amber-200 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-amber-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <strong>Aviso de Seguridad Operativa:</strong> Autorizar una agencia o sello discográfico verifica su procedencia e identidad técnica, pero <strong>NO publica automáticamente</strong> sus comunicados. Por política de Seven Rock Radio, el Modo B permanece desactivado y todos los correos entrantes producen exclusivamente borradores para revisión.
            </div>
        </div>

        {{-- Pestañas de Estado --}}
        <div class="flex flex-wrap gap-2 border-b border-[#2b2b2b] pb-2 text-xs font-semibold uppercase tracking-wider">
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'all']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'all' ? 'bg-lucille-accent text-white' : 'text-[#888] hover:text-white' }}">
                Todas ({{ $counts['all'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'authorized']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'authorized' ? 'bg-[#1e4d2b] text-[#b8e6c3] border border-[#2d7a44]' : 'text-[#888] hover:text-white' }}">
                🛡️ Autorizadas ({{ $counts['authorized'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'pending']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'pending' ? 'bg-[#b8860b]/30 text-amber-300 border border-[#b8860b]' : 'text-[#888] hover:text-white' }}">
                ⏳ Pendientes ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'suspended']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'suspended' ? 'bg-[#555] text-gray-200' : 'text-[#888] hover:text-white' }}">
                ⏸️ Suspendidas ({{ $counts['suspended'] }})
            </a>
            <a href="{{ route('admin.editorial.sources.index', ['status' => 'blocked']) }}" class="px-3 py-1.5 rounded transition-colors {{ $currentStatus === 'blocked' ? 'bg-[#7a2b2b]/40 text-red-300 border border-[#7a2b2b]' : 'text-[#888] hover:text-white' }}">
                🚫 Bloqueadas ({{ $counts['blocked'] }})
            </a>
        </div>

        {{-- Tabla de Fuentes --}}
        <div class="overflow-x-auto border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] rounded">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-[#2b2b2b] text-[#dcdcdc] font-display uppercase tracking-wider bg-[rgba(0,0,0,.3)]">
                    <tr>
                        <th class="p-3">Nombre de la Entidad</th>
                        <th class="p-3 w-32">Tipo</th>
                        <th class="p-3">Dominios Verificados & Emails</th>
                        <th class="p-3 w-28">Estado</th>
                        <th class="p-3 w-24">Incidencias</th>
                        <th class="p-3 w-40 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2b2b2b] text-[#9a9a9a]">
                    @forelse ($sources as $source)
                        <tr class="hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            <td class="p-3 align-top font-semibold text-white">
                                <div class="text-sm font-display tracking-wider">{{ $source->name }}</div>
                                @if ($source->notes)
                                    <div class="text-[10px] text-gray-400 mt-0.5 line-clamp-1 italic">{{ $source->notes }}</div>
                                @endif
                            </td>
                            <td class="p-3 align-top uppercase text-[10px] text-gray-300">
                                {{ $source->organization_type }}
                            </td>
                            <td class="p-3 align-top space-y-1">
                                @if (!empty($source->verified_domains))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($source->verified_domains as $domain)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-[#141416] border border-[#333] text-gray-300">
                                                @ {{ $domain }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                @if (!empty($source->authorized_emails))
                                    <div class="flex flex-wrap gap-1 text-[10px] text-gray-400">
                                        @foreach ($source->authorized_emails as $email)
                                            <span>{{ $email }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 align-top">
                                @if ($source->status === 'authorized')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#1e4d2b] text-[#b8e6c3] border border-[#2d7a44]">
                                        AUTORIZADA
                                    </span>
                                @elseif ($source->status === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#b8860b]/20 text-amber-300 border border-[#b8860b]">
                                        PENDIENTE
                                    </span>
                                @elseif ($source->status === 'suspended')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#333] text-gray-300 border border-[#555]">
                                        SUSPENDIDA
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#7a2b2b]/30 text-red-300 border border-[#7a2b2b]">
                                        BLOQUEADA
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 align-top text-center font-bold text-gray-300">
                                {{ $source->incidents_count }}
                            </td>
                            <td class="p-3 align-top text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Cambiar Estado --}}
                                    @if ($source->status !== 'authorized')
                                        <form action="{{ route('admin.editorial.sources.update-status', $source) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="authorized">
                                            <button type="submit" class="p-1 px-2 text-[10px] font-bold rounded bg-[#1e4d2b] text-[#b8e6c3] hover:text-white" title="Autorizar Fuente">
                                                Autorizar
                                            </button>
                                        </form>
                                    @endif

                                    @if ($source->status !== 'suspended')
                                        <form action="{{ route('admin.editorial.sources.update-status', $source) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="p-1 px-2 text-[10px] font-bold rounded bg-[#333] text-gray-300 hover:text-white" title="Suspender Temporalmente">
                                                Pausar
                                            </button>
                                        </form>
                                    @endif

                                    @if ($source->status !== 'blocked')
                                        <form action="{{ route('admin.editorial.sources.update-status', $source) }}" method="POST" class="inline" onsubmit="return confirm('¿Bloquear permanentemente esta fuente?')">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="blocked">
                                            <button type="submit" class="p-1 px-2 text-[10px] font-bold rounded bg-[#7a2b2b]/40 text-red-300 hover:text-white" title="Bloquear Remitente">
                                                Bloquear
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-[#777]">
                                No se encontraron fuentes con el criterio seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sources->hasPages())
            <div class="mt-4">
                {{ $sources->links() }}
            </div>
        @endif

        {{-- MODAL CREAR FUENTE --}}
        <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-lg border border-[#2b2b2b] bg-[#101012] p-6 rounded shadow-2xl space-y-4">
                <h3 class="font-display text-lg uppercase tracking-wider text-white">Registrar Nueva Fuente / Agencia</h3>

                <form action="{{ route('admin.editorial.sources.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Nombre de la Agencia o Sello</label>
                        <input type="text" name="name" required class="lucille-product-field w-full text-xs" placeholder="Ej. Century Media Records">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Tipo de Organización</label>
                            <select name="organization_type" required class="lucille-product-field w-full text-xs">
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
                            <select name="status" required class="lucille-product-field w-full text-xs">
                                <option value="authorized">Autorizada (Verificada)</option>
                                <option value="pending" selected>Pendiente de Verificación</option>
                                <option value="suspended">Suspendida</option>
                                <option value="blocked">Bloqueada</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Dominios Verificados (separados por coma)</label>
                        <input type="text" name="verified_domains" class="lucille-product-field w-full text-xs" placeholder="centurymedia.com, sonymusic.com">
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Correos Autorizados (separados por coma)</label>
                        <input type="text" name="authorized_emails" class="lucille-product-field w-full text-xs" placeholder="press@centurymedia.com, promo@centurymedia.com">
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Notas / Acuerdos Editoriales</label>
                        <textarea name="notes" rows="2" class="lucille-product-field w-full text-xs" placeholder="Detalles de contacto o acuerdos de cobertura..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-[#2b2b2b]">
                        <button type="button" @click="createModalOpen = false" class="lucille-button text-xs">Cancelar</button>
                        <button type="submit" class="lucille-button-solid text-xs">Guardar Fuente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
