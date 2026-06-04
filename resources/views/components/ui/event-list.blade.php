@props(['events', 'emphasizeFirst' => true])

<ul class="lucille-events-list">
    @foreach ($events as $event)
        @php($emphasized = $emphasizeFirst && $loop->first)
        @php($startsAt = \Illuminate\Support\Carbon::parse(data_get($event, 'starts_at')))
        <li class="lucille-event-row">
            <a href="{{ route('events.single', data_get($event, 'slug')) }}" class="lucille-event-link">
                <div class="lucille-event-date uppercase">
                    @if ($emphasized)
                        <span class="lucille-event-day">{{ $startsAt->format('d') }}</span>
                        <span class="lucille-event-month">{{ strtolower($startsAt->format('F')) }}</span>
                        <span class="lucille-event-year">{{ $startsAt->format('Y') }}</span>
                    @else
                        <span class="text-sm">{{ $startsAt->format('F j, Y') }}</span>
                    @endif
                </div>
                <div class="lucille-event-location">{{ data_get($event, 'location') }}</div>
                <div class="lucille-event-venue">{{ data_get($event, 'venue') }}</div>
                <div class="lucille-event-buy">{{ data_get($event, 'ticket_label', 'Details') }}</div>
            </a>
        </li>
    @endforeach
</ul>
