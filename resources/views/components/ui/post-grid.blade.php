@props(['posts'])

<div class="mx-auto max-w-[1200px] px-4 sm:px-6 mt-8 md:mt-10 grid gap-6 md:grid-cols-3 items-stretch">
    @foreach ($posts as $post)
        @php
            $title = trim((string) data_get($post, 'title', ''));
            $date = trim((string) data_get($post, 'date', ''));
            $category = trim((string) data_get($post, 'category', data_get($post, 'categories.0', 'Actualidad')));
            $excerpt = data_get($post, 'excerpt');
            $rawImage = trim((string) data_get($post, 'image', ''));
            $hasImage = (bool) data_get($post, 'has_image', $rawImage !== '');
            $imageAlt = trim((string) data_get($post, 'image_alt', $title));
            $url = trim((string) data_get($post, 'url', '#'));
            $imageUrl = $hasImage && $rawImage !== ''
                ? (\App\Support\PublicMediaUrl::normalizePublicUrl($rawImage) ?: $rawImage)
                : null;
            $cleanExcerpt = \App\Models\Post::sanitizeExcerpt(is_string($excerpt) ? $excerpt : null, is_array($excerpt) ? $excerpt : null, 160);
        @endphp
        <article class="group flex h-full min-w-0 flex-col overflow-hidden border border-lucille-line/30 bg-[#121215] transition-colors duration-200 hover:border-lucille-accent/50">
            {{-- Cabecera visual con alto contraste y respaldo explícito --}}
            <a href="{{ $url }}" class="relative block aspect-[16/10] w-full overflow-hidden bg-[#0e0e11]" aria-label="Leer artículo: {{ $title }}">
                @if ($hasImage && $imageUrl)
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $imageAlt }}"
                        onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');"
                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                        loading="lazy"
                        decoding="async"
                    >
                    <div class="hidden h-full w-full flex-col items-center justify-center p-5 bg-[#121215] text-center">
                        <span class="text-[10px] font-semibold uppercase tracking-[.22em] text-amber-400">Imagen no disponible</span>
                        <span class="mt-2 font-display text-sm uppercase tracking-wider text-lucille-heading line-clamp-2 break-words">{{ $title }}</span>
                    </div>
                @else
                    <div class="flex h-full w-full flex-col items-center justify-center p-5 bg-[radial-gradient(circle_at_center,rgba(195,39,32,0.12),rgba(16,16,18,0.98))] text-center">
                        <span class="text-[10px] font-semibold uppercase tracking-[.22em] text-gray-400">Sin imagen registrada</span>
                        <span class="mt-2 font-display text-sm uppercase tracking-wider text-lucille-heading line-clamp-2 break-words">{{ $title }}</span>
                    </div>
                @endif

                <div class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-black/80 to-transparent"></div>
                <span class="absolute left-3 top-3 inline-flex items-center rounded bg-black/85 border border-white/15 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[.16em] text-white backdrop-blur-xs">
                    {{ $category }}
                </span>
            </a>

            {{-- Contenido legible sobre superficie oscura sólida --}}
            <div class="flex flex-1 min-w-0 flex-col p-5 sm:p-6 text-left">
                @if ($date !== '')
                    <p class="text-[11px] uppercase tracking-[.14em] text-gray-400">
                        Publicado: {{ $date }}
                    </p>
                @endif

                <h3 class="mt-2 font-display text-[17px] sm:text-[18px] font-normal uppercase leading-snug tracking-[0.05em] text-white line-clamp-2 min-h-[2.9rem] break-words transition-colors duration-200 group-hover:text-lucille-accent">
                    <a href="{{ $url }}" class="hover:underline focus:outline-none">{{ $title }}</a>
                </h3>

                @if ($cleanExcerpt !== '')
                    <p class="mt-3 text-[13px] leading-relaxed text-gray-300 line-clamp-3 break-words">{{ $cleanExcerpt }}</p>
                @endif

                <div class="mt-auto pt-5">
                    <a href="{{ $url }}" class="lucille-button inline-flex min-h-[36px] items-center justify-center px-5 text-[11px] uppercase tracking-[.14em]">
                        Leer artículo &rarr;
                    </a>
                </div>
            </div>
        </article>
    @endforeach
</div>
