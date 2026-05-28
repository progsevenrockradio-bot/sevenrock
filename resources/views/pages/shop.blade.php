<x-layouts.site title="Seven Rock Radio - Tienda" description="Tienda oficial de Seven Rock Radio. Camisetas, merch y productos oficiales de tus bandas favoritas de rock.">
    @php $ui = $themeAppearance['ui_texts']; @endphp
    <x-sections.page-heading title="" />

    <section>
        <div class="lucille-content-box">
            <div class="mb-10 flex flex-col gap-4 text-[#7b7b7b] md:flex-row md:items-center md:justify-between">
                <p>Mostrando {{ count($products) }} resultados</p>
                <select class="border border-[#2b2b2b] bg-transparent px-3 py-2 text-[#7b7b7b] focus:border-lucille-accent focus:outline-none">
                    <option>Orden por defecto</option>
                    <option>Ordenar por popularidad</option>
                    <option>Ordenar por puntuación</option>
                    <option>Ordenar por más recientes</option>
                </select>
            </div>

            <div class="grid gap-x-[5%] gap-y-[60px] sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    <article class="lucille-shop-product group text-center">
                        <a href="{{ route('products.single', $product['slug']) }}" class="lucille-shop-thumb block overflow-hidden bg-[#1d1d1d]">
                            @if (! empty($product['sale']))
                                <span class="lucille-shop-sale-badge">¡Oferta!</span>
                            @endif
                            <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}" class="mx-auto w-full transition duration-500 ease-out group-hover:scale-[1.025] group-hover:opacity-90" loading="lazy">
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
