@props(['events', 'emphasizeFirst' => true])

<ul class="lucille-events-list">
    @foreach ($events as $event)
        @php($emphasized = $emphasizeFirst && $loop->first)
        <li class="lucille-event-row">
            <a href="{{ route('events.single', $event->slug) }}" class="lucille-event-link">
                <div class="lucille-event-date uppercase">
                    @if ($emphasized)
                        <span class="lucille-event-day">{{ $event->starts_at->format('d') }}</span>
                        <span class="lucille-event-month">{{ strtolower($event->starts_at->format('F')) }}</span>
                        <span class="lucille-event-year">{{ $event->starts_at->format('Y') }}</span>
                    @else
                        <span class="text-sm">{{ $event->starts_at->format('F j, Y') }}</span>
                    @endif
                </div>
                <div class="lucille-event-location">{{ $event->location }}</div>
                <div class="lucille-event-venue">{{ $event->venue }}</div>
                <div class="lucille-event-buy">{{ $event->ticket_label ?: 'Details' }}</div>
            </a>
        </li>
    @endforeach
</ul>
