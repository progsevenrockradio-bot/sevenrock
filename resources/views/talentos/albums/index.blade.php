<x-layouts.talent :title="'Talentos - Mis Álbumes'" :talent="$talent">
    <section class="space-y-6">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="mb-6 border border-[#5c3d0e] bg-[rgba(64,48,16,.2)] px-4 py-3 text-sm text-[#e6cfa8]">
                {{ session('warning') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Mis Álbumes</h1>
            <a href="{{ route('talents.albums.create') }}" class="lucille-button-solid text-sm">+ Nuevo Álbum</a>
        </div>

        @if ($albums->isEmpty())
            <div class="border border-white/10 bg-[#10161b] p-8 text-center text-sm text-[#7b7b7b]">
                No tienes álbumes todavía. <a href="{{ route('talents.albums.create') }}" class="text-white underline">Crea tu primer álbum</a>.
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($albums as $album)
                    <div class="group relative border border-white/10 bg-[#10161b] transition hover:border-white/30">
                        <div class="aspect-square overflow-hidden bg-[#1d1d1d]">
                            @if ($album->coverUrl())
                                <img src="{{ $album->coverUrl() }}" alt="{{ $album->title }}" loading="lazy" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center font-display text-4xl uppercase tracking-[.1em] text-[#3a3a3a]">
                                    {{ Str::limit($album->title, 2, '') }}
                                </div>
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-display text-sm uppercase tracking-[.12em] text-white">{{ $album->title }}</h3>
                                    @if ($album->release_date)
                                        <p class="mt-1 text-xs text-[#7b7b7b]">{{ $album->release_date->format('Y') }}</p>
                                    @endif
                                </div>
                                @if ($album->is_published)
                                    <span class="whitespace-nowrap rounded bg-[#1e4d2b] px-2 py-0.5 text-[10px] uppercase tracking-[.1em] text-[#b8e6c3]">Publicado</span>
                                @else
                                    <span class="whitespace-nowrap rounded bg-[#4d1e1e] px-2 py-0.5 text-[10px] uppercase tracking-[.1em] text-[#e6b8b8]">Borrador</span>
                                @endif
                            </div>
                            @if ($album->tracks && count($album->tracks) > 0)
                                <p class="mt-2 text-xs text-[#7b7b7b]">{{ count($album->tracks) }} canciones</p>
                            @endif
                            <div class="mt-3 flex gap-2">
                                <a href="{{ route('talents.albums.edit', $album->id) }}" class="text-xs text-[#c7d0d8] underline transition hover:text-white">Editar</a>
                                <form method="POST" action="{{ route('talents.albums.destroy', $album->id) }}" onsubmit="return confirm('¿Eliminar este álbum?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-[#c74a4a] underline transition hover:text-red-400">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.talent>
