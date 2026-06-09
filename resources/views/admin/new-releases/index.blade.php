<x-layouts.admin :title="'Nuevos Lanzamientos - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Nuevos Lanzamientos</h1>
            <p class="mt-2 text-[#7b7b7b]">Gestiona los nuevos lanzamientos musicales destacados en la página principal y sus reseñas.</p>
        </div>
        <a href="{{ route('admin.new-releases.create') }}" class="lucille-button-solid">Nuevo Lanzamiento</a>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4 w-20">Portada</th>
                    <th class="px-5 py-4">Título</th>
                    <th class="px-5 py-4">Artista</th>
                    <th class="px-5 py-4">Artista de Radio</th>
                    <th class="px-5 py-4">Fecha Lanzamiento</th>
                    <th class="px-5 py-4">Activo</th>
                    <th class="px-5 py-4">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($newReleases as $release)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4">
                            @if($release->cover_image)
                                <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="h-12 w-12 object-cover border border-[#2b2b2b]">
                            @else
                                <span class="text-xs text-gray-500">Sin portada</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">
                            {{ $release->title }}
                        </td>
                        <td class="px-5 py-4 text-[#dcdcdc]">{{ $release->artist_name }}</td>
                        <td class="px-5 py-4">{{ $release->radioArtist?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $release->released_at ? $release->released_at->format('Y-m-d') : '—' }}</td>
                        <td class="px-5 py-4">
                            @if ($release->is_active)
                                <span class="inline-flex items-center rounded-full bg-green-500/10 px-2 py-1 text-xs font-medium text-green-400 ring-1 ring-inset ring-green-500/20">Sí</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-500/10 px-2 py-1 text-xs font-medium text-red-400 ring-1 ring-inset ring-red-500/20">No</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.new-releases.edit', $release) }}" class="lucille-button">Editar</a>
                                <form
                                    action="{{ route('admin.new-releases.destroy', $release) }}"
                                    method="POST"
                                    data-confirm="¿Eliminar este lanzamiento?"
                                    data-confirm-title="Eliminar lanzamiento"
                                    data-confirm-action="Eliminar"
                                    data-confirm-tone="danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-[#7b7b7b]">No hay lanzamientos creados aún.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
