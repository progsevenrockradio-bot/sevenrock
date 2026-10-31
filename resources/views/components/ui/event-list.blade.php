@props(['events', 'emphasizeFirst' => true])

<ul class="lucille-events-list">
    @foreach ($events as $event)
        @php
            $emphasized = $emphasizeFirst && $loop->first;
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

            // Comprobar si el recinto es distinto de la ciudad/país y del título para no duplicar datos (ej. Bucaramanga / Bucaramanga)
            $hasDistinctVenue = $venue !== ''
                && mb_strtolower($venue) !== mb_strtolower($location)
                && mb_strtolower($venue) !== mb_strtolower($title);
        @endphp
        <li class="lucille-event-row {{ data_get($event, 'is_cancelled') ? 'opacity-50 grayscale' : '' }}">
            <a href="{{ $eventUrl }}" class="lucille-event-link">
                <div class="lucille-event-date uppercase">
                    @if ($startsAt && $emphasized)
                        <span class="lucille-event-day">{{ $startsAt->locale('es')->translatedFormat('d') }}</span>
                        <span class="lucille-event-month">{{ strtolower($startsAt->locale('es')->translatedFormat('F')) }}</span>
                        <span class="lucille-event-year">{{ $startsAt->locale('es')->translatedFormat('Y') }}</span>
                    @elseif ($startsAt)
                        <span class="text-sm">{{ $startsAt->locale('es')->translatedFormat('d \d\e F, Y') }}</span>
                    @else
                        <span class="text-sm">Fecha por confirmar</span>
                    @endif
                </div>
                <div class="lucille-event-location text-left">
                    <span class="block font-display text-[15px] sm:text-[16px] text-white font-bold uppercase tracking-wider group-hover:text-lucille-accent transition-colors">
                        {{ $title ?: $location }}
                    </span>
                    @if ($title && $location)
                        <span class="block text-[12px] text-[#8e8e8e] uppercase tracking-wide mt-0.5">
                            {{ $location }}
                        </span>
                    @endif
                </div>
                <div class="lucille-event-venue text-left">
                    @if ($hasDistinctVenue)
                        <span class="block text-[13px] text-[#c5c5c5] font-medium leading-tight">{{ $venue }}</span>
                        <span class="block text-[10px] text-[#666] uppercase tracking-widest mt-0.5">Recinto</span>
                    @else
                        <span class="block text-[12px] text-[#777] uppercase tracking-wider">
                            {{ $location ?: 'Lugar por confirmar' }}
                        </span>
                    @endif
                </div>
                <div class="lucille-event-buy">
                    @if(data_get($event, 'is_cancelled'))
                        <span class="text-[var(--lucille-accent)] font-bold text-[10px] tracking-widest border border-[var(--lucille-accent)] px-2 py-1">CANCELADO</span>
                    @elseif($hasRealTickets)
                        <span class="inline-block border border-lucille-accent/40 bg-lucille-accent/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-[var(--lucille-accent-readable,#ff5b52)]">
                            {{ data_get($event, 'ticket_label') && strcasecmp(data_get($event, 'ticket_label'), 'tickets') !== 0 ? data_get($event, 'ticket_label') : 'Entradas' }}
                        </span>
                    @else
                        <span class="inline-block border border-white/20 bg-white/5 px-3 py-1 text-[11px] font-medium uppercase tracking-wider text-[#dcdcdc] group-hover:border-white/40 transition-colors">
                            Ver evento
                        </span>
                    @endif
                </div>
            </a>
        </li>
    @endforeach
</ul>
