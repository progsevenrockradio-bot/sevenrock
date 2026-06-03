<x-layouts.site title="Seven Rock Radio - {{ $pageTitle ?? 'Eventos' }}" description="{{ $description ?? 'Proximos eventos, conciertos y festivales de rock. Mantente al dia con la agenda musical de Seven Rock Radio.' }}">
    <x-sections.page-heading
        :title="$pageTitle ?? 'Upcoming Shows'"
        :subtitle="$pageSubtitle ?? 'Tour Dates 2026'"
        overlay="rgba(0,0,0,0)"
    />

    <section class="white_on_black">
        <div class="lucille-events-content">
            <x-ui.event-list :events="$events" />
        </div>
    </section>
</x-layouts.site>
