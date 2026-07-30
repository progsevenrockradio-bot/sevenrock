<x-layouts.site title="Seven Rock Radio - Tienda" description="Tienda oficial de Seven Rock Radio. Camisetas, merch y productos oficiales de tus bandas favoritas de rock.">
    @php $ui = $themeAppearance['ui_texts']; @endphp
    <x-sections.page-heading title="" />

    {{-- P0-1: Toast compartido con product-single --}}
    @once
    <style>
    #cart-toast {
        position: fixed; top: 28px; right: 28px; z-index: 9999;
        display: flex; align-items: center; gap: 12px;
        background: #0f0f11; border: 1px solid rgba(195,39,32,.45);
        border-radius: 8px; padding: 14px 20px;
        box-shadow: 0 12px 40px rgba(0,0,0,.6); max-width: 340px;
        transition: opacity .3s, transform .3s; transform: translateY(-6px);
        opacity: 0; pointer-events: none;
    }
    #cart-toast.show { opacity: 1; transform: translateY(0); pointer-events: auto; }
    #cart-toast .ct-icon {
        flex-shrink: 0; width: 36px; height: 36px;
        background: rgba(195,39,32,.15); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    #cart-toast .ct-title {
        font-family: var(--lucille-display-font, 'Oswald', sans-serif);
        font-size: 13px; letter-spacing: .08em; text-transform: uppercase;
        color: #dcdcdc; margin-bottom: 2px;
    }
    #cart-toast .ct-msg { font-size: 11px; color: #7b7b7b; line-height: 1.4; }
    #cart-toast .ct-close {
        margin-left: auto; background: none; border: none; color: #555;
        cursor: pointer; font-size: 18px; line-height: 1; padding: 2px 4px; transition: color .2s;
    }
    #cart-toast .ct-close:hover { color: #c32720; }
    /* P3-2: Fade-in del grid de productos */
    @keyframes shopCardIn {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .lucille-shop-product { animation: shopCardIn 0.45s ease both; }
    .lucille-shop-product:nth-child(2) { animation-delay: .06s; }
    .lucille-shop-product:nth-child(3) { animation-delay: .12s; }
    .lucille-shop-product:nth-child(4) { animation-delay: .18s; }
    .lucille-shop-product:nth-child(n+5) { animation-delay: .24s; }
    </style>
    <div id="cart-toast" role="status" aria-live="polite">
        <div class="ct-icon">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#c32720" stroke-width="2.2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div>
            <div class="ct-title">🛒 Próximamente</div>
            <div class="ct-msg">La tienda estará disponible muy pronto. ¡Gracias por tu interés!</div>
        </div>
        <button class="ct-close" onclick="hideCartToast()" aria-label="Cerrar">&times;</button>
    </div>
    <script>
    function showCartToast() {
        const t = document.getElementById('cart-toast');
        t.classList.add('show');
        clearTimeout(window._cartToastTimer);
        window._cartToastTimer = setTimeout(hideCartToast, 4500);
    }
    function hideCartToast() {
        document.getElementById('cart-toast')?.classList.remove('show');
    }
    </script>
    @endonce

    <section>
        <div class="lucille-content-box">
            <div class="mb-10 flex flex-col gap-4 text-[#7b7b7b] md:flex-row md:items-start md:justify-between">
                <div>
                    <p>Mostrando {{ count($products) }} resultados</p>
                    {{-- P1-4: Nota de moneda --}}
                    <p class="mt-1 text-[11px] uppercase tracking-[.09em] text-[#444]">
                        <svg class="inline-block mr-1 -mt-0.5" viewBox="0 0 16 16" width="10" height="10" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="7"/><path d="M8 4v4l3 1.5"/></svg>
                        Precios en GBP (£) · Envío desde UK
                    </p>
                </div>
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
                            @if (!empty($product['image']))
                                <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}" class="mx-auto w-full transition duration-500 ease-out group-hover:scale-[1.025] group-hover:opacity-90" loading="lazy">
                            @else
                                {{-- P3-3: Placeholder de marca para productos sin imagen --}}
                                <div class="flex flex-col items-center justify-center py-14 gap-3 opacity-25">
                                    <img src="{{ asset('assets/lucille/logo.png') }}" alt="Seven Rock Radio" class="w-16">
                                </div>
                            @endif
                        </a>
                        <h2 class="mt-4 font-display text-sm font-light text-[#dcdcdc] transition duration-300 group-hover:text-lucille-accent">{{ $product['title'] }}</h2>
                        {{-- P3-1: Espaciado mejorado entre precio y botón --}}
                        <p class="mt-3 text-base text-lucille-accent">
                            @if (! empty($product['regular_price']))
                                <del class="mr-2 text-[#7b7b7b]">{{ $product['regular_price'] }}</del>
                            @endif
                            {{ $product['price'] }}
                        </p>
                        {{-- P0-1: Botón redirige a página de producto (no al checkout roto) --}}
                        <a href="{{ route('products.single', $product['slug']) }}" class="lucille-shop-button mt-5">{{ $ui['add_to_cart'] }}</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.site>
