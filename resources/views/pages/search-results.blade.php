<x-layouts.site title="Seven Rock Radio - Búsqueda" description="Resultados de búsqueda en Seven Rock Radio">
    <x-sections.page-heading title="Búsqueda" overlay="rgba(0,0,0,0)" :image="$themeAppearance['background_url'] ?? ''" />

    <section>
        <div class="lucille-content-box">
            <form method="GET" action="{{ route('search') }}" class="mb-10">
                <div class="flex gap-3">
                    <input
                        type="text"
                        name="q"
                        value="{{ $query ?? '' }}"
                        placeholder="Buscar en Seven Rock Radio..."
                        class="lucille-form-field flex-1"
                    >
                    <button type="submit" class="lucille-button-solid">Buscar</button>
                </div>
            </form>

            @if ($query)
                <h2 class="mb-6 font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">
                    Resultados para: "{{ e($query) }}"
                </h2>

                @if ($results->isEmpty())
                    <p class="text-[#7b7b7b]">No se encontraron resultados para tu búsqueda.</p>
                @else
                    <div class="divide-y divide-[#2b2b2b]">
                        @foreach ($results as $item)
                            @php
                                $data = $item['data'];
                                $pub = $data->published_at ?? null;
                            @endphp
                            <div class="flex items-start gap-4 py-5">
                                <span class="mt-1 rounded border border-[#2b2b2b] px-2 py-1 text-[10px] uppercase tracking-[.18em] text-[#7b7b7b]">
                                    {{ $item['type'] }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    @php $url = '#'; @endphp
                                    @if ($item['type'] === 'Post' && $data->slug && $pub)
                                        @php $url = url($pub->format('Y').'/'.$pub->format('m').'/'.$pub->format('d').'/'.$data->slug); @endphp
                                    @elseif ($item['type'] === 'Album' && $data->slug)
                                        @php $url = route('albums.single', $data->slug); @endphp
                                    @elseif ($item['type'] === 'Event' && $data->slug)
                                        @php $url = route('events.single', $data->slug); @endphp
                                    @elseif ($item['type'] === 'Video' && $data->slug)
                                        @php $url = route('videos.single', $data->slug); @endphp
                                    @endif
                                    <a href="{{ $url }}" class="font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc] hover:text-lucille-accent">
                                        {{ $data->title ?? $data->name ?? '' }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="text-center text-[#7b7b7b]">Escribe algo para buscar en el sitio.</p>
            @endif
        </div>
    </section>
</x-layouts.site>
