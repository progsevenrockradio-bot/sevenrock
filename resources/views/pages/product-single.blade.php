<x-layouts.site :title="$product['title'] . ' - Seven Rock Radio'">
    @php $ui = $themeAppearance['ui_texts']; @endphp
    <section>
        <div class="lucille-content-box">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-start">
                <div class="lucille-product-gallery">
                    <div class="border border-[#2b2b2b] bg-[#1b1b1b] p-4">
                        <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}" class="block w-full" loading="lazy">
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
                    <p class="mt-5 max-w-2xl text-[15px] leading-8 text-[#7b7b7b]">
                        {{ $product['description'] }} There is contrast stitching and raw edge detail around the collar, sleeves and bottom hem line.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        @if (! empty($product['external_payment_url']))
                            <a href="{{ $product['external_payment_url'] }}" target="_blank" rel="nofollow noopener" class="lucille-button-solid">
                                {{ $product['external_payment_label'] ?? $ui['add_to_cart'] }} ↗
                            </a>
                        @else
                            <label class="font-display text-sm uppercase tracking-[.3em] text-[#7b7b7b]" for="quantity">{{ $ui['quantity'] }}</label>
                            <input id="quantity" type="number" min="1" value="1" class="lucille-product-qty">
                            <button type="button" class="lucille-button-solid">{{ $ui['add_to_cart'] }}</button>
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
                            <p>{{ $product['description'] }} Each t-shirt is unique with vintage finish and mini ribbed neckline. The word T-shirt became part of American English by the 1920s, and appeared in the Merriam-Webster Dictionary.</p>
                            <p class="mt-5">Following World War II, it became common to see veterans wearing their uniform trousers with their T-shirts as casual clothing.</p>
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
