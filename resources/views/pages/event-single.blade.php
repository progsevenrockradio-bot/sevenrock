@php
    $categories = $event['categories'] ?? [];
    $content = $event['content'] ?? [];
    $ticketUrl = $event['ticket_url'] ?? '#';
    $ticketLabel = $event['ticket_label'] ?? 'Tickets';
    $poster = $event['poster'] ?? '';
    $venueUrl = $event['venue_url'] ?? '#';
    $facebookUrl = $event['facebook_url'] ?? '';
    $shareUrl = request()->fullUrl();
    $shareTitle = trim((string) ($event['title'] ?? ''));
    $twitterShareUrl = 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareTitle) . '&url=' . rawurlencode($shareUrl);
    $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl);
    $pinterestShareUrl = 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($shareUrl)
        . ($poster !== '' ? '&media=' . rawurlencode($poster) : '')
        . '&description=' . rawurlencode($shareTitle);
    $eventDesc = \Illuminate\Support\Str::limit(strip_tags(implode(' ', $event['content'] ?? [])), 250);
    $normalizedPoster = \App\Support\PublicMediaUrl::normalizePublicUrl($poster);

    $eventSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $shareTitle,
        'description' => $eventDesc ?: $shareTitle,
        'eventStatus' => !empty($event['is_cancelled']) ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => [
            '@type' => 'Place',
            'name' => $event['venue'] ?: ($event['location'] ?: 'Recinto del evento'),
            'address' => $event['location'] ?: 'Colombia',
        ],
    ];

    if (!empty($event['starts_at_iso'])) {
        $eventSchema['startDate'] = $event['starts_at_iso'];
    }

    if (!empty($normalizedPoster)) {
        $eventSchema['image'] = [$normalizedPoster];
    }

    if (!empty($ticketUrl) && $ticketUrl !== '#') {
        $eventSchema['offers'] = [
            '@type' => 'Offer',
            'url' => $ticketUrl,
            'availability' => !empty($event['is_cancelled']) ? 'https://schema.org/Discontinued' : 'https://schema.org/InStock',
        ];
    }
@endphp

<x-layouts.site 
    title="Seven Rock Radio - {{ $event['title'] }}"
    :og-image="$normalizedPoster"
    :description="$eventDesc"
>
    @push('jsonld')
    <script type="application/ld+json">
    {!! json_encode($eventSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    @endpush
    <section class="lucille-event-single-shell py-8 md:py-12">
        <div class="mx-auto max-w-[1200px] px-4 sm:px-6">
            {{-- Encabezado de la Ficha --}}
            <div class="lucille-flat-surface relative overflow-hidden p-6 sm:p-10 text-center mb-8 border border-white/10">
                @if(!empty($event['is_cancelled']))
                    <div class="absolute top-6 -right-10 rotate-45 bg-[#c32720] px-12 py-1 text-[11px] font-bold uppercase tracking-widest text-white shadow-lg">
                        Cancelado
                    </div>
                @endif
                @if (! empty($categories))
                    <div class="mb-4 flex flex-wrap justify-center gap-2 text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                        @foreach ($categories as $category)
                            <span class="border border-white/10 bg-white/5 px-3 py-1 text-[#bcbcbc] rounded-sm">{{ $category }}</span>
                        @endforeach
                    </div>
                @endif

                <h1 class="font-display text-3xl sm:text-5xl lg:text-6xl uppercase tracking-[.08em] font-extrabold {{ !empty($event['is_cancelled']) ? 'text-[#7b7b7b] line-through' : 'text-white' }}">
                    {{ $event['title'] }}
                </h1>
                <p class="mt-3 text-xs sm:text-sm uppercase tracking-[.25em] text-[#c32720] font-display font-semibold">
                    Conciertos y Eventos Oficiales
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3 text-xs uppercase tracking-[.15em] text-[#dcdcdc] font-mono">
                    <span class="border border-white/10 bg-black/40 px-4 py-2 rounded-sm flex items-center gap-2">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" class="text-[#c32720]"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <span>{{ $event['date'] }}</span>
                    </span>
                    <span class="border border-white/10 bg-black/40 px-4 py-2 rounded-sm flex items-center gap-2">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" class="text-[#c32720]"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span>{{ $event['time'] }}</span>
                    </span>
                </div>
            </div>

            {{-- Contenido Principal: Datos y Cartel --}}
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,460px)] items-start">
                {{-- Columna izquierda: Información, Acción principal y Descripción --}}
                <div class="lucille-flat-surface p-6 sm:p-8 border border-white/10">
                    {{-- Cuadrícula de detalles clave --}}
                    <div class="grid gap-4 sm:grid-cols-2 pb-6 border-b border-white/10">
                        <div class="flex items-start gap-3">
                            <span class="p-2.5 rounded-sm bg-white/5 border border-white/10 text-[#c32720] shrink-0" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            </span>
                            <div class="min-w-0">
                                <span class="block text-[10px] text-[#757575] uppercase tracking-wider font-mono">Fecha</span>
                                <time itemprop="startDate" class="block text-sm font-semibold text-white mt-0.5">{{ $event['date'] }}</time>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="p-2.5 rounded-sm bg-white/5 border border-white/10 text-[#c32720] shrink-0" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            </span>
                            <div class="min-w-0">
                                <span class="block text-[10px] text-[#757575] uppercase tracking-wider font-mono">Hora / Zona horaria</span>
                                <span class="block text-sm font-semibold text-white mt-0.5">{{ $event['time'] }}</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="p-2.5 rounded-sm bg-white/5 border border-white/10 text-[#c32720] shrink-0" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            </span>
                            <div class="min-w-0">
                                <span class="block text-[10px] text-[#757575] uppercase tracking-wider font-mono">Ciudad / Ubicación</span>
                                <span class="block text-sm font-semibold text-white mt-0.5">{{ $event['location'] ?: 'Por confirmar' }}</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="p-2.5 rounded-sm bg-white/5 border border-white/10 text-[#c32720] shrink-0" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                            </span>
                            <div class="min-w-0">
                                <span class="block text-[10px] text-[#757575] uppercase tracking-wider font-mono">Recinto / Escenario</span>
                                @if ($venueUrl && $venueUrl !== '#')
                                    <a href="{{ $venueUrl }}" target="_blank" rel="noreferrer" class="block text-sm font-semibold text-white hover:text-lucille-accent transition-colors mt-0.5 truncate">
                                        {{ $event['venue'] ?: 'Por confirmar' }} ↗
                                    </a>
                                @else
                                    <span class="block text-sm font-semibold text-white mt-0.5">{{ $event['venue'] ?: 'Por confirmar' }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Acción principal y accesos --}}
                    <div class="py-6 border-b border-white/10 flex flex-wrap items-center gap-3">
                        @if ($ticketUrl && $ticketUrl !== '#')
                            <a href="{{ $ticketUrl }}" target="_blank" rel="noreferrer" class="lucille-button inline-flex items-center gap-2">
                                <span>{{ strcasecmp($ticketLabel, 'Tickets') === 0 ? 'Comprar entradas' : $ticketLabel }}</span>
                                <span aria-hidden="true">↗</span>
                            </a>
                        @endif
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noreferrer" class="border border-white/20 bg-white/5 hover:bg-white/10 hover:border-white/40 text-white px-5 py-2.5 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-2 rounded-sm">
                                <span>Ver evento en Facebook</span>
                                <span aria-hidden="true">↗</span>
                            </a>
                        @endif
                        <a href="{{ route('events') }}" class="border border-white/10 text-[#8e8e8e] hover:text-white hover:border-white/25 px-4 py-2.5 text-xs uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 rounded-sm">
                            <span>← Volver a la agenda</span>
                        </a>
                    </div>

                    {{-- Descripción del Evento --}}
                    <div class="pt-6">
                        <h3 class="font-display text-base uppercase tracking-wider font-bold text-white mb-4">Información del concierto</h3>
                        <div class="space-y-4 text-sm leading-relaxed text-[#7b7b7b]" itemprop="description">
                            @if (!empty($content))
                                @foreach ($content as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            @else
                                <p>No hay descripción detallada disponible para este evento.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Compartir --}}
                    <div class="mt-8 pt-6 border-t border-white/10 flex flex-wrap items-center gap-4 text-xs text-[#757575]">
                        <span class="font-display uppercase tracking-wider font-bold text-[#dcdcdc]">Compartir:</span>
                        <a href="{{ $twitterShareUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-lucille-accent transition-colors">X (Twitter)</a>
                        <span class="text-white/20">•</span>
                        <a href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-lucille-accent transition-colors">Facebook</a>
                        <span class="text-white/20">•</span>
                        <a href="{{ $pinterestShareUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-lucille-accent transition-colors">Pinterest</a>
                    </div>
                </div>

                {{-- Columna derecha: Cartel del evento y Video/Embed --}}
                <div class="space-y-6">
                    <div class="lucille-flat-surface p-3 border border-white/10">
                        <div class="overflow-hidden bg-[#111] rounded-sm">
                            <img
                                src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($poster) }}"
                                alt="Cartel oficial: {{ $event['title'] }}"
                                itemprop="image"
                                class="h-auto w-full object-cover rounded-sm shadow-md"
                                loading="lazy">
                        </div>
                    </div>

                    @if (!empty($event['embed']))
                        <div class="lucille-flat-surface p-3 border border-white/10">
                            <div class="relative aspect-video overflow-hidden rounded-sm bg-[#111]">
                                <iframe
                                    src="{{ $event['embed'] }}"
                                    title="{{ $event['title'] }}"
                                    class="absolute inset-0 h-full w-full"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (!empty($event['map']))
            <div class="mx-auto mt-8 max-w-[1200px] px-4 sm:px-6">
                <div class="lucille-flat-surface p-4 border border-white/10">
                    <h3 class="font-display text-sm uppercase tracking-wider font-bold text-white mb-3">Ubicación y mapa del recinto</h3>
                    <div class="lucille-event-map rounded-sm overflow-hidden border border-white/10">
                        <div class="lucille-event-map-frame">
                            <iframe src="{{ $event['map'] }}" loading="lazy" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>
</x-layouts.site>
