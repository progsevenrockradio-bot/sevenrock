<x-layouts.site title="Seven Rock Radio - {{ $pageTitle ?? 'Próximos conciertos' }}" description="{{ $description ?? 'Próximos eventos, conciertos y festivales de rock. Mantente al día con la agenda musical de Seven Rock Radio.' }}">
    <x-sections.page-heading
        :title="$pageTitle ?? 'Próximos conciertos'"
        :subtitle="$pageSubtitle ?? 'Fechas de conciertos'"
        overlay="rgba(0,0,0,0)"
    />

    <section class="white_on_black">
        <div class="lucille-events-content">
            <x-ui.event-list :events="$events" />
        </div>
    </section>
</x-layouts.site>
