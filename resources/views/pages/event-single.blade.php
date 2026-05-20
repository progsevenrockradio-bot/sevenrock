@php
    $categories = $event['categories'] ?? [];
    $content = $event['content'] ?? [];
    $ticketUrl = $event['ticket_url'] ?? '#';
    $ticketLabel = $event['ticket_label'] ?? 'Tickets';
    $poster = $event['poster'] ?? '';
    $venueUrl = $event['venue_url'] ?? '#';
    $facebookUrl = $event['facebook_url'] ?? '';
@endphp

<x-layouts.site title="Seven Rock Radio - {{ $event['title'] }}">
    <section class="lucille-event-single-shell">
        <div class="mx-auto max-w-[1200px] px-6 pt-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.62)] px-6 py-8 text-center md:px-10 md:py-10">
                @if (! empty($categories))
                    <div class="mb-4 flex flex-wrap justify-center gap-2 text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                        @foreach ($categories as $category)
                            <span class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] px-3 py-1 text-[#bcbcbc]">{{ $category }}</span>
                        @endforeach
                    </div>
                @endif

                <h1 class="font-display text-4xl uppercase tracking-[.12em] text-[#dcdcdc] md:text-[4.35rem]">{{ $event['title'] }}</h1>
                <p class="mt-3 text-[11px] uppercase tracking-[.36em] text-[#7b7b7b]">Upcoming shows 2026</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3 text-[12px] uppercase tracking-[.18em] text-[#dcdcdc]">
                    <span class="border border-[#2b2b2b] px-4 py-2">{{ $event['date'] }}</span>
                    <span class="border border-[#2b2b2b] px-4 py-2">{{ $event['time'] }}</span>
                </div>
            </div>

            <div class="mt-7 grid gap-8 lg:grid-cols-[minmax(0,.92fr)_minmax(400px,540px)]">
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-5 md:p-7">
                    <div class="grid gap-4 md:grid-cols-[repeat(2,minmax(0,1fr))]">
                        <div class="lucille-event-detail-entry mb-0">
                            <span class="lucille-detail-icon lucille-detail-icon-calendar" aria-hidden="true"></span>
                            <time itemprop="startDate">{{ $event['date'] }}</time>
                        </div>
                        <div class="lucille-event-detail-entry mb-0">
                            <span class="lucille-detail-icon lucille-detail-icon-clock" aria-hidden="true"></span>
                            {{ $event['time'] }}
                        </div>
                        <div class="lucille-event-detail-entry mb-0">
                            <span class="lucille-detail-icon lucille-detail-icon-marker" aria-hidden="true"></span>
                            <span>{{ $event['location'] }}</span>
                        </div>
                        <div class="lucille-event-detail-entry mb-0">
                            <span class="lucille-detail-icon lucille-detail-icon-pin" aria-hidden="true"></span>
                            @if ($venueUrl && $venueUrl !== '#')
                                <a href="{{ $venueUrl }}" target="_blank" rel="noreferrer" class="transition hover:text-lucille-accent">
                                    {{ $event['venue'] }}
                                </a>
                            @else
                                <span>{{ $event['venue'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-7 flex flex-wrap gap-2">
                        @if ($ticketUrl && $ticketUrl !== '#')
                            <a href="{{ $ticketUrl }}" target="_blank" rel="noreferrer" class="lucille-button">{{ $ticketLabel }}</a>
                        @endif
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noreferrer" class="lucille-button">Check event on Facebook</a>
                        @endif
                    </div>

                    <div class="mt-6 space-y-4 text-[14px] leading-6 text-[#7b7b7b]" itemprop="description">
                        @foreach ($content as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>

                    <div class="mt-9 flex items-center gap-4 text-[#757575]">
                        <span class="font-display text-sm uppercase tracking-[.08em] text-[#dcdcdc]">Share:</span>
                        <a href="#" class="transition duration-300 hover:text-lucille-accent">Twitter</a>
                        <a href="#" class="transition duration-300 hover:text-lucille-accent">Facebook</a>
                        <a href="#" class="transition duration-300 hover:text-lucille-accent">Pinterest</a>
                    </div>
                </div>

                <div class="space-y-3 lg:-mt-4">
                    <div class="border border-[#2b2b2b] bg-black/40 p-2">
                        <img
                            src="{{ $poster }}"
                            alt="{{ $event['title'] }}"
                            itemprop="image"
                            class="event-single-poster h-auto w-full object-cover"
                        >
                    </div>

                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-2">
                        <div class="relative aspect-video overflow-hidden border border-[#2b2b2b] bg-[#111]">
                            <iframe
                                src="{{ $event['embed'] }}"
                                title="{{ $event['title'] }}"
                                class="absolute inset-0 h-full w-full"
                                allowfullscreen
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto mt-8 max-w-[1240px] px-6 pb-8">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-2">
                <div class="lucille-event-map">
                    <div class="lucille-event-map-frame">
                        <iframe src="{{ $event['map'] }}" loading="lazy" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
