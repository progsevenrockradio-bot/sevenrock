<x-layouts.site title="Seven Rock Radio - Events">
    <x-sections.page-heading
        title="Upcoming Shows"
        subtitle="Tour Dates 2026"
        overlay="rgba(0,0,0,0)"
    />

    <section class="white_on_black">
        <div class="lucille-events-content">
            <x-ui.event-list :events="$events" />
        </div>
    </section>
</x-layouts.site>
