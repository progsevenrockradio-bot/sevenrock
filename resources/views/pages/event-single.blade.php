<x-layouts.site title="Seven Rock Radio - {{ $event['title'] }}">
    <x-sections.page-heading
        :title="$event['title']"
        overlay="rgba(0,0,0,0)"
        :categories="$event['categories']"
    />

    <section itemscope itemtype="http://schema.org/Event">
        <div class="lucille-event-single-content">
            <div class="grid gap-8 md:grid-cols-2">
                <div class="md:pr-[15px]">
                    <div class="event_short_details">
                        <div class="lucille-event-detail-entry">
                            <span class="lucille-detail-icon lucille-detail-icon-calendar" aria-hidden="true"></span>
                            <time itemprop="startDate">{{ $event['date'] }}</time>
                        </div>
                        <div class="lucille-event-detail-entry">
                            <span class="lucille-detail-icon lucille-detail-icon-clock" aria-hidden="true"></span>
                            {{ $event['time'] }}
                        </div>
                        <div class="lucille-event-detail-entry" itemprop="location" itemscope itemtype="http://schema.org/Place">
                            <span class="lucille-detail-icon lucille-detail-icon-marker" aria-hidden="true"></span>
                            <span itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">{{ $event['location'] }}</span>
                        </div>
                        <div class="lucille-event-detail-entry">
                            <span class="lucille-detail-icon lucille-detail-icon-pin" aria-hidden="true"></span>
                            <a href="{{ $event['venue_url'] }}" target="_self" class="transition hover:text-lucille-accent">
                                <span itemprop="name">{{ $event['venue'] }}</span>
                            </a>
                        </div>
                    </div>

                    <div class="small_content_padding pt-[10px]">
                        <div class="mb-[10px] flex flex-wrap gap-1">
                            <a href="{{ $event['ticket_url'] }}" target="_self" class="lucille-button">Tickets</a>
                            <a href="{{ $event['facebook_url'] }}" target="_blank" rel="noreferrer" class="lucille-button">Check Event on Facebook</a>
                        </div>

                        <div class="space-y-5 text-[14px] leading-[26px] text-[#7b7b7b]" itemprop="description">
                            @foreach ($event['content'] as $paragraph)
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
                </div>

                <div class="md:pl-[15px]">
                    <img src="{{ asset($event['poster']) }}" alt="{{ $event['title'] }}" itemprop="image" class="mb-[10px] w-full max-w-[450px]">
                    <div class="relative aspect-video w-full overflow-hidden bg-[#111]">
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

        <div class="lucille-event-map">
            <div class="lucille-event-map-frame">
                <iframe src="{{ $event['map'] }}" loading="lazy" allowfullscreen></iframe>
            </div>
        </div>
    </section>
</x-layouts.site>
