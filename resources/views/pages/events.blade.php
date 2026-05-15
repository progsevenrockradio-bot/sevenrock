<x-layouts.site title="Seven Rock Radio - Events">
    <x-sections.page-heading
        title="Events"
        subtitle="On Tour"
        image="assets/lucille/microphone-1206364_1920.jpg"
        overlay="rgba(21,21,21,.88)"
    />

    <section class="white_on_black">
        <div class="lucille-events-content">
            <x-ui.event-list :events="$events" />
        </div>
    </section>
</x-layouts.site>
