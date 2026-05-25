<x-layouts.admin :title="'Contenido de talentos'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.18)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-6">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Moderación</div>
            <h1 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-white">Contenido subido por talentos</h1>
        </div>

        <form method="GET" action="{{ route('admin.talents.media') }}" class="grid gap-3 md:grid-cols-[1.2fr_1fr_auto]">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por talento" class="lucille-product-field w-full">
            <select name="type" class="lucille-product-field w-full">
                <option value="">Todos los tipos</option>
                @foreach (['photo' => 'Foto', 'mp3' => 'MP3', 'document' => 'Documento', 'video' => 'Video'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="lucille-button-solid">Filtrar</button>
        </form>

        <div class="overflow-x-auto border border-white/10 bg-[#10161b]">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-black/20 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="px-4 py-3">Talento</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Archivo</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @foreach ($media as $item)
                        <tr class="text-[#d8d8d8]">
                            <td class="px-4 py-4">{{ $item->talent?->band_name ?? 'N/D' }}</td>
                            <td class="px-4 py-4">{{ ucfirst($item->type) }}</td>
                            <td class="px-4 py-4">{{ $item->title ?: $item->filename }}</td>
                            <td class="px-4 py-4">{{ $item->created_at?->format('d/m/Y H:i') ?? 'N/D' }}</td>
                            <td class="px-4 py-4">
                                <form method="POST" action="{{ route('admin.talents.media.delete', $item) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid" data-confirm="¿Eliminar este archivo del talento?" data-confirm-title="Eliminar contenido" data-confirm-action="Eliminar" data-confirm-tone="danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $media->links() }}
        </div>
    </section>
</x-layouts.admin>
