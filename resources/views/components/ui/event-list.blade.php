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
                <div class="lucille-event-location">{{ data_get($event, 'location') }}</div>
                <div class="lucille-event-venue">{{ data_get($event, 'venue') }}</div>
                <div class="lucille-event-buy">
                    @if(data_get($event, 'is_cancelled'))
                        <span class="text-[var(--lucille-accent)] font-bold text-[10px] tracking-widest border border-[var(--lucille-accent)] px-2 py-1">CANCELADO</span>
                    @else
                        {{ data_get($event, 'ticket_label') ?: 'Detalles' }}
                    @endif
                </div>
            </a>
        </li>
    @endforeach
</ul>
