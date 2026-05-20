@props(['ticker'])

@php
    $items = collect(data_get($ticker, 'items', []));
@endphp

@if ($items->isNotEmpty())
    @php
        $loopItems = $items->concat($items)->values();
    @endphp

    <section class="border-y border-white/6 bg-[rgba(0,0,0,.38)] py-1 text-[#dcdcdc]">
        <div class="mx-auto flex max-w-[1180px] flex-col gap-1 px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-1.5">
                <div class="flex items-center gap-1.5">
                    <span class="font-display text-[8px] uppercase tracking-[.3em] text-[#dcdcdc]">{{ data_get($ticker, 'label', 'Editorial feed') }}</span>
                    <span class="h-px w-5 bg-white/10"></span>
                    <span class="font-display text-[8px] uppercase tracking-[.3em] text-lucille-accent">{{ data_get($ticker, 'subtitle', 'Latest headlines') }}</span>
                </div>
                <span class="hidden h-px flex-1 bg-white/10 md:block"></span>
                <a href="{{ route('blog') }}" class="font-display text-[7px] uppercase tracking-[.3em] text-[#7b7b7b] transition hover:text-white">
                    View blog
                </a>
            </div>

            <div class="relative overflow-hidden">
                <div class="srr-ticker-track flex w-max items-stretch gap-1.5 pr-2">
                    @foreach ($loopItems as $item)
                        @php
                            $url = data_get($item, 'url');
                            $label = data_get($item, 'label', 'NEWS');
                            $title = data_get($item, 'title', '');
                            $meta = data_get($item, 'meta', '');
                            $tone = data_get($item, 'tone', 'muted');
                            $image = trim((string) data_get($item, 'image', ''));
                            $toneClass = match ($tone) {
                                'accent' => 'border-lucille-accent/35 bg-[rgba(195,39,32,.08)]',
                                'warning' => 'border-[#b7ad9f]/25 bg-[rgba(183,173,159,.06)]',
                                'story' => 'border-white/8 bg-[rgba(7,16,33,.34)]',
                                default => 'border-white/8 bg-[rgba(255,255,255,.03)]',
                            };
                        @endphp

                        @if ($url)
                            <a href="{{ $url }}" class="group flex min-w-[190px] items-center gap-1.5 border px-2 py-1 transition hover:border-lucille-accent hover:bg-[rgba(195,39,32,.10)] {{ $toneClass }}">
                                @if ($image !== '')
                                    <img src="{{ $image }}" alt="" class="h-7 w-7 shrink-0 object-cover">
                                @endif
                                <span class="inline-flex h-4 shrink-0 items-center border border-lucille-accent/35 px-1 text-[7px] font-semibold uppercase tracking-[.22em] text-lucille-accent">
                                    {{ $label }}
                                </span>
                                <span class="min-w-0 flex-1 leading-tight">
                                    <span class="block truncate font-display text-[10px] uppercase tracking-[.12em] text-[#f1f1f1] transition group-hover:text-white">
                                        {{ $title }}
                                    </span>
                                    @if ($meta !== '')
                                        <span class="mt-0.5 block truncate text-[8px] uppercase tracking-[.18em] text-[#7b7b7b]">
                                            {{ $meta }}
                                        </span>
                                    @endif
                                </span>
                            </a>
                        @else
                            <div class="flex min-w-[190px] items-center gap-1.5 border px-2 py-1 {{ $toneClass }}">
                                @if ($image !== '')
                                    <img src="{{ $image }}" alt="" class="h-7 w-7 shrink-0 object-cover">
                                @endif
                                <span class="inline-flex h-4 shrink-0 items-center border border-lucille-accent/35 px-1 text-[7px] font-semibold uppercase tracking-[.22em] text-lucille-accent">
                                    {{ $label }}
                                </span>
                                <span class="min-w-0 flex-1 leading-tight">
                                    <span class="block truncate font-display text-[10px] uppercase tracking-[.12em] text-[#f1f1f1]">
                                        {{ $title }}
                                    </span>
                                    @if ($meta !== '')
                                        <span class="mt-0.5 block truncate text-[8px] uppercase tracking-[.18em] text-[#7b7b7b]">
                                            {{ $meta }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @once
        <style>
            @keyframes srr-ticker-scroll {
                0% {
                    transform: translateX(0);
                }

                100% {
                    transform: translateX(-50%);
                }
            }

            .srr-ticker-track {
                animation: srr-ticker-scroll 60s linear infinite;
            }

            .srr-ticker-track:hover {
                animation-play-state: paused;
            }
        </style>
    @endonce
@endif
