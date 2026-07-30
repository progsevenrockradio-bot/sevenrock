<x-layouts.site :title="$product['title'] . ' - Seven Rock Radio'">
    {{-- ══ Toast Carrito ══ --}}
    @once
    <style>
    #cart-toast {
        position: fixed;
        top: 28px;
        right: 28px;
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 12px;
        background: #0f0f11;
        border: 1px solid rgba(195,39,32,.45);
        border-radius: 8px;
        padding: 14px 20px;
        box-shadow: 0 12px 40px rgba(0,0,0,.6);
        max-width: 340px;
        transition: opacity .3s, transform .3s;
        transform: translateY(-6px);
        opacity: 0;
        pointer-events: none;
    }
    #cart-toast.show {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    #cart-toast .ct-icon {
        flex-shrink: 0;
        width: 36px; height: 36px;
        background: rgba(195,39,32,.15);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    #cart-toast .ct-title {
        font-family: var(--lucille-display-font, 'Oswald', sans-serif);
        font-size: 13px;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #dcdcdc;
        margin-bottom: 2px;
    }
    #cart-toast .ct-msg {
        font-size: 11px;
        color: #7b7b7b;
        line-height: 1.4;
    }
    #cart-toast .ct-close {
        margin-left: auto;
        background: none;
        border: none;
        color: #555;
        cursor: pointer;
        font-size: 18px;
        line-height: 1;
        padding: 2px 4px;
        transition: color .2s;
    }
    #cart-toast .ct-close:hover { color: #c32720; }
    </style>
    <div id="cart-toast" role="status" aria-live="polite">
        <div class="ct-icon">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#c32720" stroke-width="2.2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
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
    @php $ui = $themeAppearance['ui_texts']; @endphp
    {{-- Breadcrumbs P2-2 --}}
    <nav aria-label="Breadcrumb" class="lucille-content-box pt-6 pb-0">
        <ol class="flex items-center gap-2 text-[11px] uppercase tracking-[.1em] text-[#555]">
            <li><a href="{{ route('home') }}" class="hover:text-lucille-accent transition-colors">Inicio</a></li>
            <li class="select-none">/</li>
            <li><a href="{{ route('shop') }}" class="hover:text-lucille-accent transition-colors">Tienda</a></li>
            <li class="select-none">/</li>
            <li class="text-[#888] truncate max-w-[180px]">{{ $product['title'] }}</li>
        </ol>
    </nav>
    <section>
        <div class="lucille-content-box">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-start">
                <div class="lucille-product-gallery">
                    <div class="border border-[#2b2b2b] bg-[#1b1b1b] p-4">
                        @if(!empty($product['image']))
                            <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}" class="block w-full" loading="lazy">
                        @else
                            <div class="flex flex-col items-center justify-center py-20 gap-4 opacity-30">
                                <img src="{{ asset('assets/lucille/logo.png') }}" alt="Seven Rock Radio" class="w-24 opacity-50">
                                <span class="font-display text-xs uppercase tracking-[.15em] text-[#7b7b7b]">Sin imagen disponible</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="lucille-product-summary">
                    <h1 class="font-display text-[38px] font-light uppercase leading-none tracking-[.08em] text-[#dcdcdc] md:text-[48px]">
                        {{ $product['title'] }}
                    </h1>
                    <p class="mt-4 text-[28px] leading-none text-[#dcdcdc]">
                        @if (! empty($product['regular_price']))
                            <del class="mr-3 text-[#7b7b7b]">{{ $product['regular_price'] }}</del>
                        @endif
                        {{ $product['price'] }}
                    </p>
                    {{-- P1-4: Nota de moneda GBP --}}
                    <p class="mt-2 text-[11px] text-[#555] uppercase tracking-[.1em]">
                        <svg class="inline-block mr-1 -mt-0.5" viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="7"/><path d="M8 4v4l3 1.5"/></svg>
                        Precio en GBP · Impuestos según tu país · Envío desde UK
                    </p>
                    <p class="mt-5 max-w-2xl text-[15px] leading-8 text-[#7b7b7b]">
                        {{ $product['description'] }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        @if (! empty($product['external_payment_url']))
                            <a href="{{ $product['external_payment_url'] }}" target="_blank" rel="nofollow noopener" class="lucille-button-solid">
                                {{ $product['external_payment_label'] ?? $ui['add_to_cart'] }} ↗
                            </a>
                        @else
                            <label class="font-display text-sm uppercase tracking-[.3em] text-[#7b7b7b]" for="quantity">{{ $ui['quantity'] }}</label>
                            <input id="quantity" type="number" min="1" value="1" class="lucille-product-qty">
                            {{-- P0-1+P1-1: Botón con toast "Próximamente" en lugar de checkout roto --}}
                            <button
                                type="button"
                                class="lucille-button-solid relative"
                                onclick="showCartToast()"
                                aria-label="Añadir al carrito (próximamente disponible)"
                            >
                                <span>{{ $ui['add_to_cart'] }}</span>
                            </button>
                        @endif
                    </div>

                    <div class="mt-8 border-t border-[#2b2b2b] pt-6 text-[14px] leading-7 text-[#7b7b7b]">
                        <span class="text-[#dcdcdc]">{{ $ui['category'] }}</span>
                        <a href="{{ route('shop') }}" class="ml-2 text-[#7b7b7b] transition hover:text-lucille-accent">{{ $product['category'] ?? 'T-SHIRTS' }}</a>
                    </div>

                    <div class="mt-10" x-data="{ tab: 'description' }">
                        <div class="flex gap-6 border-b border-[#2b2b2b]">
                            <button type="button" @click="tab = 'description'" :class="tab === 'description' ? 'text-[#dcdcdc] border-b-2 border-lucille-accent -mb-px' : 'text-[#7b7b7b]'" class="pb-3 font-display text-sm uppercase tracking-[.25em] transition">{{ $ui['description'] }}</button>
                            <button type="button" @click="tab = 'reviews'" :class="tab === 'reviews' ? 'text-[#dcdcdc] border-b-2 border-lucille-accent -mb-px' : 'text-[#7b7b7b]'" class="pb-3 font-display text-sm uppercase tracking-[.25em] transition">{{ $ui['reviews'] }}</button>
                        </div>

                        <div x-show="tab === 'description'" x-cloak class="py-6 text-[15px] leading-8 text-[#7b7b7b]">
                            <p>{{ $product['description'] }}</p>
                        </div>

                        <div x-show="tab === 'reviews'" x-cloak class="py-6 text-[15px] leading-8 text-[#7b7b7b]">
                            <p>{{ $ui['no_reviews'] }}</p>
                            <p class="mt-4">{{ str_replace(':title', $product['title'], $ui['be_first_review']) }}</p>
                            <form class="mt-6 space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <input type="text" placeholder="{{ $ui['your_name'] }}" class="lucille-product-field">
                                    <input type="email" placeholder="{{ $ui['email_address'] }}" class="lucille-product-field">
                                </div>
                                <textarea rows="5" placeholder="{{ $ui['write_comment'] }}" class="lucille-product-field w-full"></textarea>
                                <button type="button" class="lucille-button-solid">{{ $ui['submit'] }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if ($relatedProducts)
                <div class="mt-16">
                    <h2 class="mb-8 font-display text-2xl font-light uppercase tracking-[.14em] text-[#dcdcdc]">{{ $ui['related_products'] }}</h2>
                    <div class="grid gap-x-[5%] gap-y-[50px] sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($relatedProducts as $related)
                            <article class="lucille-shop-product group text-center">
                                <a href="{{ route('products.single', $related['slug']) }}" class="lucille-shop-thumb block overflow-hidden bg-[#1d1d1d]">
                                    @if (! empty($related['sale']))
                                        <span class="lucille-shop-sale-badge">¡Oferta!</span>
                                    @endif
                                    <img src="{{ $related['image'] }}" alt="{{ $related['title'] }}" class="mx-auto w-full transition duration-500 ease-out group-hover:scale-[1.025] group-hover:opacity-90" loading="lazy">
                                </a>
                                <h3 class="mt-4 font-display text-sm font-light text-[#dcdcdc] transition duration-300 group-hover:text-lucille-accent">{{ $related['title'] }}</h3>
                                <p class="mt-2 text-base text-lucille-accent">
                                    @if (! empty($related['regular_price']))
                                        <del class="mr-2 text-[#7b7b7b]">{{ $related['regular_price'] }}</del>
                                    @endif
                                    {{ $related['price'] }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
