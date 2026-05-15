<x-layouts.site title="Seven Rock Radio - Shop">
    @php $ui = $themeAppearance['ui_texts']; @endphp
    <x-sections.page-heading title="" />

    <section>
        <div class="lucille-content-box">
            <div class="mb-10 flex flex-col gap-4 text-[#7b7b7b] md:flex-row md:items-center md:justify-between">
                <p>Showing all {{ count($products) }} results</p>
                <select class="border border-[#2b2b2b] bg-transparent px-3 py-2 text-[#7b7b7b] focus:border-lucille-accent focus:outline-none">
                    <option>Default sorting</option>
                    <option>Sort by popularity</option>
                    <option>Sort by average rating</option>
                    <option>Sort by latest</option>
                </select>
            </div>

            <div class="grid gap-x-[5%] gap-y-[60px] sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    <article class="lucille-shop-product group text-center">
                        <a href="{{ route('products.single', $product['slug']) }}" class="lucille-shop-thumb block overflow-hidden bg-[#1d1d1d]">
                            @if (! empty($product['sale']))
                                <span class="lucille-shop-sale-badge">Sale!</span>
                            @endif
                            <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}" class="mx-auto w-full transition duration-500 ease-out group-hover:scale-[1.025] group-hover:opacity-90">
                        </a>
                        <h2 class="mt-4 font-display text-sm font-light text-[#dcdcdc] transition duration-300 group-hover:text-lucille-accent">{{ $product['title'] }}</h2>
                        <p class="mt-2 text-base text-lucille-accent">
                            @if (! empty($product['regular_price']))
                                <del class="mr-2 text-[#7b7b7b]">{{ $product['regular_price'] }}</del>
                            @endif
                            {{ $product['price'] }}
                        </p>
                        <a href="{{ route('products.single', $product['slug']) }}" class="lucille-shop-button mt-4">{{ $ui['add_to_cart'] }}</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.site>
