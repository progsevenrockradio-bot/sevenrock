@props(['posts'])

<div class="mt-8 md:mt-10 grid gap-6 md:grid-cols-3">
    @foreach ($posts as $post)
        @php
            $title = data_get($post, 'title');
            $date = data_get($post, 'date');
            $category = data_get($post, 'category', data_get($post, 'categories.0', 'Blog'));
            $excerpt = data_get($post, 'excerpt');
            $image = data_get($post, 'image');
            $url = data_get($post, 'url', route('posts.single', ['year' => now()->format('Y'), 'month' => now()->format('m'), 'day' => now()->format('d'), 'slug' => 'inspiration']));
            $imageUrl = \App\Support\PublicMediaUrl::normalizePublicUrl($image)
                ?: (str_starts_with((string) $image, 'http') ? (string) $image : ($image ? asset((string) $image) : asset('assets/lucille/logo.png')));

            $rawExcerpt = (string) $excerpt;
            if (str_starts_with(trim($rawExcerpt), '{') || str_starts_with(trim($rawExcerpt), '[')) {
                $decoded = json_decode($rawExcerpt, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $rawExcerpt = data_get($decoded, 'rendered')
                        ?? data_get($decoded, 'raw')
                        ?? data_get($decoded, '0.value')
                        ?? data_get($decoded, '0.content.html')
                        ?? (is_string(reset($decoded)) ? reset($decoded) : '');
                }
            }
            $rawExcerpt = html_entity_decode((string)$rawExcerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rawExcerpt = html_entity_decode($rawExcerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rawExcerpt = strip_tags($rawExcerpt);
            $rawExcerpt = str_replace(['\r', '\n', '\t', '\/'], [' ', ' ', ' ', '/'], $rawExcerpt);
            $cleanExcerpt = trim(preg_replace('/\s+/u', ' ', $rawExcerpt) ?? '');
            $cleanExcerpt = \Illuminate\Support\Str::limit($cleanExcerpt, 160, '...');
        @endphp
        <article class="group relative h-full min-h-[420px] overflow-hidden bg-lucille-surface text-center border border-lucille-line/30 flex flex-col justify-between">
            @if ($image)
                <!-- Fondo difuminado -->
                <div class="absolute inset-0 bg-cover bg-center opacity-40 blur-md transition duration-500 group-hover:scale-[1.05]" style="background-image: url('{{ $imageUrl }}');"></div>
                <!-- Imagen contenida -->
                <div class="absolute inset-0 bg-contain bg-center bg-no-repeat transition duration-500 group-hover:scale-[1.03]" style="background-image: url('{{ $imageUrl }}');"></div>
                <!-- Capa oscurecedora para texto -->
                <div class="absolute inset-0 bg-black/75 transition duration-300 group-hover:bg-black/60"></div>
            @else
                <div class="absolute inset-0 bg-cover bg-center opacity-25 blur-md" style="background-image: url('{{ asset('assets/lucille/logo.png') }}');"></div>
                <div class="absolute inset-0 bg-black/75"></div>
            @endif
            <div class="relative z-10 flex h-full min-h-[420px] flex-col items-center px-6 sm:px-8 py-8">
                <h3 class="font-display text-[19px] sm:text-[21px] font-normal uppercase leading-snug tracking-[0.06em] text-lucille-heading line-clamp-2 transition duration-300 group-hover:text-lucille-accent">{{ $title }}</h3>
                <p class="mt-2 text-xs italic tracking-wider text-lucille-body/80">{{ $date }} &middot; {{ $category }}</p>
                @if ($cleanExcerpt !== '')
                    <p class="mt-4 text-[13px] leading-relaxed text-lucille-body line-clamp-3">{{ $cleanExcerpt }}</p>
                @endif
                <a href="{{ $url }}" class="lucille-button mt-auto min-h-[36px] px-5 text-[11px] uppercase tracking-[.14em]">Leer más &rarr;</a>
            </div>
        </article>
    @endforeach
</div>
