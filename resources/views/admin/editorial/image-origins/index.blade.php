@php
    $theme = $themeSettings ?? \App\Models\ThemeSetting::current();
@endphp
<x-layouts.admin :title="'Orígenes de Imágenes Autorizados - ' . $theme->site_name">
    <div
        x-data="{
            createModalOpen: false,
            editModalOpen: false,
            editOrigin: { id: null, label: '', host: '', path_prefix: '', require_https: true, is_active: true, scope: 'manual_editorial', notes: '' },
            openEdit(origin) {
                this.editOrigin = Object.assign({}, origin);
                this.editModalOpen = true;
            }
        }"
        @keydown.escape.window="createModalOpen = false; editModalOpen = false"
        class="space-y-6"
    >
        @if (session('success'))
            <div class="flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                <span>{{ session('success') }}</span>
                <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-xs text-rose-200">
                <div class="font-semibold text-rose-300 mb-1">Revisa los siguientes campos:</div>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Cabecera --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">
                    Orígenes de Imágenes Autorizados
                </h1>
                <p class="mt-1 text-sm text-gray-400">
                    Administra dominios y prefijos de ruta autorizados para la validación técnica de imágenes en noticias y efemérides, sin modificar código.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('admin.editorial.sources.index') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                    ← Fuentes Editoriales
                </a>
                <button type="button" @click="createModalOpen = true" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
                    + Autorizar Nuevo Origen
                </button>
            </div>
        </div>

        {{-- Separación técnica vs editorial --}}
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-xs text-amber-200 space-y-1.5">
            <div>
                <strong>Validación técnica vs. acreditación editorial:</strong> Autorizar un dominio o espacio (como la cuenta propia de Cloudinary) permite únicamente la comprobación técnica del archivo remoto (HTTPS, protección anti-SSRF, redirecciones seguras, tamaño, Content-Type y firma binaria).
            </div>
            <div class="text-amber-200/80">
                Esta autorización técnica <strong>no acredita derechos ni licencias de uso</strong>. Los orígenes con ámbito <em>Edición manual</em> no amplían los criterios de la ingesta automática de correos. Todos los cambios quedan registrados en el historial de auditoría.
            </div>
        </div>

        {{-- Tabla de Orígenes Administrables --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="p-3.5 sm:px-5">Etiqueta</th>
                            <th class="p-3.5">Host y Prefijo de Ruta</th>
                            <th class="p-3.5 w-36">Ámbito</th>
                            <th class="p-3.5 w-24">HTTPS</th>
                            <th class="p-3.5 w-24">Estado</th>
                            <th class="p-3.5 w-44 text-right sm:pr-5">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-gray-300">
                        @forelse ($origins as $origin)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-3.5 sm:px-5 align-top">
                                    <div class="font-semibold text-white text-sm">{{ $origin->label }}</div>
                                    @if ($origin->notes)
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $origin->notes }}</div>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top font-mono text-xs text-gray-200">
                                    <div>{{ $origin->host }}</div>
                                    @if ($origin->path_prefix)
                                        <div class="text-[11px] text-sky-300 mt-0.5">Ruta: {{ $origin->path_prefix }}*</div>
                                    @else
                                        <div class="text-[11px] text-gray-500 mt-0.5">Cualquier ruta pública del host</div>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top">
                                    @if ($origin->scope === \App\Models\AuthorizedImageOrigin::SCOPE_MANUAL_EDITORIAL)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-sky-500/20 bg-sky-500/10 text-sky-300">
                                            Edición manual
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-amber-500/20 bg-amber-500/10 text-amber-300">
                                            Manual + automático
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top">
                                    @if ($origin->require_https)
                                        <span class="text-emerald-400 font-medium">Obligatorio</span>
                                    @else
                                        <span class="text-gray-400">Opcional</span>
                                    @endif
                                </td>
                                <td class="p-3.5 align-top">
                                    @if ($origin->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                                            ACTIVO
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-white/10 bg-white/5 text-gray-400">
                                            INACTIVO
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 sm:pr-5 align-top text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEdit(@js([
                                                'id' => $origin->id,
                                                'label' => $origin->label,
                                                'host' => $origin->host,
                                                'path_prefix' => $origin->path_prefix ?? '',
                                                'require_https' => (bool) $origin->require_https,
                                                'is_active' => (bool) $origin->is_active,
                                                'scope' => $origin->scope,
                                                'notes' => $origin->notes ?? '',
                                                'update_url' => route('admin.editorial.image-origins.update', $origin),
                                            ]))"
                                            class="h-7 px-2 text-[10px] font-medium rounded-md border border-white/10 bg-white/5 text-gray-200 hover:bg-white/10"
                                        >
                                            Editar
                                        </button>

                                        <form action="{{ route('admin.editorial.image-origins.toggle', $origin) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="h-7 px-2 text-[10px] font-medium rounded-md border border-amber-500/30 bg-amber-500/10 text-amber-200 hover:bg-amber-500/20">
                                                {{ $origin->is_active ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>

                                        <form action="{{ route('admin.editorial.image-origins.destroy', $origin) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este origen de imágenes autorizado?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="h-7 px-2 text-[10px] font-medium rounded-md border border-rose-900/40 bg-rose-950/20 text-rose-300 hover:bg-rose-900/30">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-500">
                                    No hay orígenes personalizados registrados todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Dominios integrados del sistema --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h2 class="text-xs font-medium uppercase tracking-wider text-gray-300 mb-2">Orígenes base integrados en el sistema</h2>
            <p class="text-xs text-gray-400 mb-3">Estos dominios están activos por defecto tanto para edición manual como para validación editorial.</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($builtInHosts as $host)
                    <span class="px-2.5 py-1 rounded-md text-[11px] font-mono bg-white/5 border border-white/10 text-gray-300">{{ $host }}</span>
                @endforeach
            </div>
        </div>

        {{-- Modal Crear Origen --}}
        <div x-show="createModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="create-origin-title" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#101012] p-6 shadow-2xl space-y-4">
                <h3 id="create-origin-title" class="font-display text-lg uppercase tracking-wider text-white">Autorizar Nuevo Origen de Imágenes</h3>

                <form action="{{ route('admin.editorial.image-origins.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Nombre / Etiqueta</label>
                        <input type="text" name="label" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Cloudinary Seven Rock Radio (dutx97jzy)">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Dominio (Host)</label>
                            <input type="text" name="host" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs font-mono text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="res.cloudinary.com">
                        </div>
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Prefijo de ruta (Recomendado en CDNs)</label>
                            <input type="text" name="path_prefix" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs font-mono text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="/dutx97jzy/image/upload/">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Ámbito</label>
                            <select name="scope" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                                <option value="manual_editorial" selected>Solo edición manual (recomendado)</option>
                                <option value="all">Edición manual e ingesta</option>
                            </select>
                        </div>
                        <div class="flex flex-col justify-end gap-2 pb-1">
                            <label class="flex items-center gap-2 text-gray-300 cursor-pointer">
                                <input type="checkbox" name="require_https" value="1" checked class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720]">
                                <span>Exigir HTTPS obligatorio</span>
                            </label>
                            <label class="flex items-center gap-2 text-gray-300 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720]">
                                <span>Activo inmediatamente</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Notas operativas</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Motivo o espacio concreto autorizado..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-white/10">
                        <button type="button" @click="createModalOpen = false" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-gray-300 text-xs hover:bg-white/10">Cerrar</button>
                        <button type="submit" class="inline-flex items-center justify-center h-8 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold">Guardar Origen</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Editar Origen --}}
        <div x-show="editModalOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="edit-origin-title" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#101012] p-6 shadow-2xl space-y-4">
                <h3 id="edit-origin-title" class="font-display text-lg uppercase tracking-wider text-white">Editar Origen de Imágenes</h3>

                <form :action="editOrigin.update_url" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Nombre / Etiqueta</label>
                        <input type="text" name="label" x-model="editOrigin.label" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Dominio (Host)</label>
                            <input type="text" name="host" x-model="editOrigin.host" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs font-mono text-gray-100 focus:border-[#c32720] focus:outline-none">
                        </div>
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Prefijo de ruta</label>
                            <input type="text" name="path_prefix" x-model="editOrigin.path_prefix" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs font-mono text-gray-100 focus:border-[#c32720] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block uppercase tracking-wider text-gray-400 mb-1">Ámbito</label>
                            <select name="scope" x-model="editOrigin.scope" required class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                                <option value="manual_editorial">Solo edición manual</option>
                                <option value="all">Edición manual e ingesta</option>
                            </select>
                        </div>
                        <div class="flex flex-col justify-end gap-2 pb-1">
                            <label class="flex items-center gap-2 text-gray-300 cursor-pointer">
                                <input type="checkbox" name="require_https" value="1" :checked="editOrigin.require_https" class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720]">
                                <span>Exigir HTTPS obligatorio</span>
                            </label>
                            <label class="flex items-center gap-2 text-gray-300 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" :checked="editOrigin.is_active" class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720]">
                                <span>Activo</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block uppercase tracking-wider text-gray-400 mb-1">Notas operativas</label>
                        <textarea name="notes" rows="2" x-model="editOrigin.notes" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-white/10">
                        <button type="button" @click="editModalOpen = false" class="inline-flex items-center justify-center h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-gray-300 text-xs hover:bg-white/10">Cerrar</button>
                        <button type="submit" class="inline-flex items-center justify-center h-8 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>

