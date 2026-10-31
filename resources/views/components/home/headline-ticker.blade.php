@props(['ticker'])

@php
    $items = collect(data_get($ticker, 'items', []))->values();
@endphp

@if ($items->isNotEmpty())
    <section 
        x-data="{ userPaused: false }" 
        aria-label="Titulares de actualidad" 
        class="border-y border-white/6 bg-[rgba(0,0,0,.38)] py-1 text-[#dcdcdc]"
    >
        <div class="mx-auto flex max-w-[1180px] flex-col gap-1 px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-1.5">
                <div class="flex items-center gap-1.5">
                    <span class="font-display text-[8px] uppercase tracking-[.3em] text-[#dcdcdc]">{{ data_get($ticker, 'label', 'Feed editorial') }}</span>
                    <span class="h-px w-5 bg-white/10" aria-hidden="true"></span>
                    <span class="font-display text-[8px] uppercase tracking-[.3em] text-[var(--lucille-accent-readable,#ff5b52)]">{{ data_get($ticker, 'subtitle', 'Últimos titulares') }}</span>
                </div>
                <span class="hidden h-px flex-1 bg-white/10 md:block" aria-hidden="true"></span>
                <div class="flex items-center gap-2">
                    <button 
                        type="button" 
                        @click="userPaused = !userPaused" 
                        :aria-pressed="userPaused.toString()" 
                        :aria-label="userPaused ? 'Reanudar movimiento de titulares' : 'Pausar movimiento de titulares'"
                        class="inline-flex h-8 w-8 items-center justify-center text-[#7b7b7b] transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent focus-visible:ring-offset-1 focus-visible:ring-offset-black"
                    >
                        <template x-if="!userPaused">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <rect x="6" y="4" width="4" height="16" rx="1"></rect>
                                <rect x="14" y="4" width="4" height="16" rx="1"></rect>
                            </svg>
                        </template>
                        <template x-if="userPaused">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                        </template>
                    </button>
                    <a href="{{ route('blog') }}" class="font-display text-[7px] uppercase tracking-[.3em] text-[#7b7b7b] transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent focus-visible:ring-offset-1 focus-visible:ring-offset-black">
                        Ver blog
                    </a>
                </div>
            </div>

            <div class="relative overflow-hidden srr-ticker-viewport">
                <div 
                    class="srr-ticker-track flex w-max items-stretch"
                    :class="{ 'srr-ticker-paused': userPaused }"
                >
                    {{-- 1. Pista canónica accesible para lectores de pantalla y navegación por teclado --}}
                    <div class="flex items-stretch gap-1.5 pr-1.5 shrink-0">
                        @foreach ($items as $item)
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
                                <a href="{{ $url }}" class="group flex min-w-[190px] items-center gap-1.5 border px-2 py-1 transition hover:border-lucille-accent hover:bg-[rgba(195,39,32,.10)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent focus-visible:ring-offset-1 focus-visible:ring-offset-black {{ $toneClass }}">
                                    @if ($image !== '')
                                        <img src="{{ $image }}" alt="" class="h-7 w-7 shrink-0 object-cover" loading="lazy">
                                    @endif
                                    <span class="inline-flex h-4 shrink-0 items-center border border-lucille-accent/35 px-1 text-[7px] font-semibold uppercase tracking-[.22em] text-[var(--lucille-accent-readable,#ff5b52)]">
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
                                        <img src="{{ $image }}" alt="" class="h-7 w-7 shrink-0 object-cover" loading="lazy">
                                    @endif
                                    <span class="inline-flex h-4 shrink-0 items-center border border-lucille-accent/35 px-1 text-[7px] font-semibold uppercase tracking-[.22em] text-[var(--lucille-accent-readable,#ff5b52)]">
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

                    {{-- 2. Pista decorativa duplicada para continuidad visual, excluida de accesibilidad --}}
                    <div class="flex items-stretch gap-1.5 pr-1.5 shrink-0 pointer-events-none srr-ticker-clone" aria-hidden="true">
                        @foreach ($items as $item)
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
                                <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="group flex min-w-[190px] items-center gap-1.5 border px-2 py-1 {{ $toneClass }}">
                                    @if ($image !== '')
                                        <img src="{{ $image }}" alt="" class="h-7 w-7 shrink-0 object-cover" loading="lazy" aria-hidden="true">
                                    @endif
                                    <span class="inline-flex h-4 shrink-0 items-center border border-lucille-accent/35 px-1 text-[7px] font-semibold uppercase tracking-[.22em] text-[var(--lucille-accent-readable,#ff5b52)]">
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
                                </a>
                            @else
                                <div class="flex min-w-[190px] items-center gap-1.5 border px-2 py-1 {{ $toneClass }}">
                                    @if ($image !== '')
                                        <img src="{{ $image }}" alt="" class="h-7 w-7 shrink-0 object-cover" loading="lazy" aria-hidden="true">
                                    @endif
                                    <span class="inline-flex h-4 shrink-0 items-center border border-lucille-accent/35 px-1 text-[7px] font-semibold uppercase tracking-[.22em] text-[var(--lucille-accent-readable,#ff5b52)]">
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

            .srr-ticker-track:hover,
            .srr-ticker-track:focus-within {
                animation-play-state: paused;
            }

            .srr-ticker-paused {
                animation-play-state: paused !important;
            }

            @media (prefers-reduced-motion: reduce) {
                .srr-ticker-viewport {
                    overflow-x: auto !important;
                    scrollbar-width: thin;
                    scroll-behavior: smooth;
                }

                .srr-ticker-track {
                    animation: none !important;
                }

                .srr-ticker-clone {
                    display: none !important;
                }
            }
        </style>
    @endonce
@endif