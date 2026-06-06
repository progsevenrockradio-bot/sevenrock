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
        <li class="lucille-event-row">
            <a href="{{ $eventUrl }}" class="lucille-event-link">
                <div class="lucille-event-date uppercase">
                    @if ($startsAt && $emphasized)
                        <span class="lucille-event-day">{{ $startsAt->format('d') }}</span>
                        <span class="lucille-event-month">{{ strtolower($startsAt->format('F')) }}</span>
                        <span class="lucille-event-year">{{ $startsAt->format('Y') }}</span>
                    @elseif ($startsAt)
                        <span class="text-sm">{{ $startsAt->format('F j, Y') }}</span>
                    @else
                        <span class="text-sm">Date TBD</span>
                    @endif
                </div>
                <div class="lucille-event-location">{{ data_get($event, 'location') }}</div>
                <div class="lucille-event-venue">{{ data_get($event, 'venue') }}</div>
                <div class="lucille-event-buy">{{ data_get($event, 'ticket_label', 'Details') }}</div>
            </a>
        </li>
    @endforeach
</ul>
