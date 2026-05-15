@props(['posts'])

<div class="mt-[60px] grid md:grid-cols-3">
    @foreach ($posts as $post)
        @php
            $title = data_get($post, 'title');
            $date = data_get($post, 'date');
            $category = data_get($post, 'category', data_get($post, 'categories.0', 'Blog'));
            $excerpt = data_get($post, 'excerpt');
            $image = data_get($post, 'image');
            $url = data_get($post, 'url', route('posts.single', ['year' => now()->format('Y'), 'month' => now()->format('m'), 'day' => now()->format('d'), 'slug' => 'inspiration']));
        @endphp
        <article class="group relative min-h-[455px] overflow-hidden bg-[#111722] text-center">
            @if ($image)
                <div class="absolute inset-0 bg-cover bg-center transition duration-500 group-hover:scale-[1.03]" style="background-image: url('{{ str_starts_with($image, 'http') ? $image : asset($image) }}');"></div>
                <div class="absolute inset-0 bg-[rgba(21,21,21,.48)] transition duration-300 group-hover:bg-[rgba(21,21,21,.34)]"></div>
            @else
                <div class="absolute inset-0 bg-[rgba(7,16,33,.45)]"></div>
            @endif
            <div class="relative z-10 flex min-h-[455px] flex-col items-center justify-center px-11 py-10">
                <h3 class="font-display text-[26px] font-normal uppercase leading-tight tracking-[1px] text-[#f9f9f9] transition duration-300 group-hover:text-lucille-accent">{{ $title }}</h3>
                <p class="mt-3 text-sm italic leading-[26px] text-[#cbcbcb]">{{ $date }} by admin in {{ $category }}</p>
                <p class="mt-7 text-[14px] leading-[26px] text-[#d8d8d8]">{{ $excerpt }}</p>
                <a href="{{ $url }}" class="lucille-button mt-6 min-h-[36px] px-4 text-[11px]">Read More</a>
            </div>
        </article>
    @endforeach
</div>
