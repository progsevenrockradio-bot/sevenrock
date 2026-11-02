@props(['events', 'emphasizeFirst' => true])

@php
    $eventsCollection = collect($events ?? []);
@endphp

@if ($eventsCollection->isEmpty())
    <div class="lucille-flat-surface mx-auto max-w-4xl p-8 sm:p-12 text-center my-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white/5 border border-white/10 text-[#c32720] mb-4">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <h3 class="font-display text-xl uppercase tracking-wider text-white font-bold mb-2">No hay eventos programados</h3>
        <p class="text-sm text-[#7b7b7b] max-w-md mx-auto">
            Actualmente no hay fechas de conciertos disponibles en esta categoría. Consulta periódicamente nuestra agenda o vuelve a los próximos eventos.
        </p>
    </div>
@else
    <ul class="lucille-events-list">
        @foreach ($eventsCollection as $index => $event)
            @php
                $isFeatured = $emphasizeFirst && $loop->first;
                $startsAtValue = data_get($event, 'starts_at');
                $startsAt = filled($startsAtValue) ? \Illuminate\Support\Carbon::parse($startsAtValue) : null;
                $eventSlug = (string) data_get($event, 'slug', '');
                $eventUrl = $eventSlug !== ''
                    ? route('events.single', $eventSlug)
                    : (string) data_get($event, 'url', '#');

                $title = (string) data_get($event, 'title', '');
                $location = trim((string) data_get($event, 'location', ''));
                $venue = trim((string) data_get($event, 'venue', ''));
                $ticketUrl = trim((string) data_get($event, 'ticket_url', ''));
                $hasRealTickets = !empty($ticketUrl) && $ticketUrl !== '#';

                // Comprobar si el recinto es distinto de la ciudad/país y del título para no duplicar datos
                $hasDistinctVenue = $venue !== ''
                    && mb_strtolower($venue) !== mb_strtolower($location)
                    && mb_strtolower($venue) !== mb_strtolower($title);

                $accessibleDate = $startsAt ? $startsAt->locale('es')->translatedFormat('d \d\e F, Y') : 'Fecha por confirmar';
            @endphp
            <li class="lucille-event-row {{ $isFeatured ? 'lucille-event-row--featured' : '' }} {{ data_get($event, 'is_cancelled') ? 'opacity-50 grayscale' : '' }}">
                <a href="{{ $eventUrl }}" class="lucille-event-link group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent rounded-sm" aria-label="{{ $title }} - {{ $accessibleDate }}">
                    {{-- Bloque Fecha: Reserva anchura suficiente (w-[180px]) --}}
                    <div class="lucille-event-date">
                        @if ($startsAt && $isFeatured)
                            {{-- Primer evento destacado: número grande al fondo + mes centrado en ambas dimensiones dentro del número --}}
                            <div class="lucille-event-date-featured" aria-hidden="true">
                                <span class="lucille-event-day-ghost">
                                    {{ $startsAt->locale('es')->translatedFormat('d') }}
                                </span>
                                <div class="lucille-event-month-overlay">
                                    <span class="lucille-event-month-text">
                                        {{ strtolower($startsAt->locale('es')->translatedFormat('F')) }}
                                    </span>
                                    <span class="sr-only">{{ $startsAt->locale('es')->translatedFormat('Y') }}</span>
                                </div>
                            </div>
                        @elseif ($startsAt)
                            {{-- Eventos sucesivos: fecha convencional discreta y legible --}}
                            <div class="lucille-event-date-standard">
                                <span class="text-xs sm:text-[13px] font-sans text-[#7b7b7b] group-hover:text-[#dcdcdc] transition-colors">
                                    {{ $startsAt->locale('es')->translatedFormat('F d, Y') }}
                                </span>
                            </div>
                        @else
                            <div class="lucille-event-date-standard">
                                <span class="text-xs uppercase text-[#7b7b7b] tracking-wider">Por confirmar</span>
                            </div>
                        @endif
                    </div>

                    {{-- Evento / Artista y Ciudad --}}
                    <div class="lucille-event-location text-left min-w-0">
                        <span class="block font-display {{ $isFeatured ? 'text-[14px] sm:text-[15px] text-[#dcdcdc] font-normal uppercase tracking-widest' : 'text-[13px] sm:text-[14px] text-[#7b7b7b] font-normal tracking-wide' }} group-hover:text-lucille-accent transition-colors truncate">
                            {{ $location ?: $title }}
                        </span>
                        @if ($title && $location && $location !== $title)
                            <span class="block text-[11px] sm:text-[12px] text-[#7b7b7b] uppercase tracking-wider mt-0.5 truncate">
                                {{ $title }}
                            </span>
                        @endif
                    </div>

                    {{-- Recinto / Escenario --}}
                    <div class="lucille-event-venue text-left min-w-0">
                        @if ($hasDistinctVenue)
                            <span class="block font-display {{ $isFeatured ? 'text-[14px] sm:text-[15px] text-[#a0a0a0] uppercase tracking-wider' : 'text-[13px] sm:text-[14px] text-[#7b7b7b]' }} font-normal leading-tight truncate group-hover:text-[#dcdcdc] transition-colors">
                                {{ $venue }}
                            </span>
                        @else
                            <span class="block font-display text-[13px] sm:text-[14px] text-[#7b7b7b] uppercase tracking-wider truncate">
                                {{ $location ?: 'Lugar por confirmar' }}
                            </span>
                        @endif
                    </div>

                    {{-- Acción: Entradas vs Ver evento --}}
                    <div class="lucille-event-buy text-right">
                        @if(data_get($event, 'is_cancelled'))
                            <span class="inline-block border border-[var(--lucille-accent)]/60 bg-[var(--lucille-accent)]/10 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-[var(--lucille-accent)] rounded-sm">
                                CANCELADO
                            </span>
                        @elseif($hasRealTickets)
                            <span class="lucille-event-action-link font-display {{ $isFeatured ? 'text-[13px] sm:text-[14px] uppercase tracking-widest text-[#dcdcdc]' : 'text-[12px] sm:text-[13px] text-[#7b7b7b]' }} group-hover:text-lucille-accent transition-colors">
                                {{ data_get($event, 'ticket_label') && strcasecmp(data_get($event, 'ticket_label'), 'tickets') !== 0 ? data_get($event, 'ticket_label') : 'Entradas' }}
                            </span>
                        @else
                            <span class="lucille-event-action-link font-display {{ $isFeatured ? 'text-[13px] sm:text-[14px] uppercase tracking-widest text-[#dcdcdc]' : 'text-[12px] sm:text-[13px] text-[#7b7b7b]' }} group-hover:text-lucille-accent transition-colors">
                                Ver evento
                            </span>
                        @endif
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
@endif
