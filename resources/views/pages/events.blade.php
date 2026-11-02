<x-layouts.site title="Seven Rock Radio - {{ $pageTitle ?? 'Próximos conciertos' }}" description="{{ $description ?? 'Próximos eventos, conciertos y festivales de rock. Mantente al día con la agenda musical de Seven Rock Radio.' }}">
    <x-sections.page-heading
        :title="$pageTitle ?? 'Próximos conciertos'"
        :subtitle="$pageSubtitle ?? 'Agenda oficial de rock'"
        overlay="rgba(0,0,0,0)"
    />

    <section class="py-10 md:py-16">
        <div class="lucille-events-content">
            {{-- Navegación de filtros de eventos --}}
            <nav aria-label="Filtros de eventos" class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-8 pb-4 border-b border-white/10">
                <a href="{{ route('events') }}" class="px-4 py-2 text-xs uppercase tracking-wider font-display font-bold rounded-sm border transition-colors {{ request()->routeIs('events') || request()->routeIs('events.upcoming') ? 'border-lucille-accent bg-lucille-accent/15 text-white' : 'border-white/10 text-[#7b7b7b] hover:text-white hover:border-white/30 bg-black/30' }}">
                    Próximos
                </a>
                <a href="{{ route('events.all') }}" class="px-4 py-2 text-xs uppercase tracking-wider font-display font-bold rounded-sm border transition-colors {{ request()->routeIs('events.all') ? 'border-lucille-accent bg-lucille-accent/15 text-white' : 'border-white/10 text-[#7b7b7b] hover:text-white hover:border-white/30 bg-black/30' }}">
                    Todos los eventos
                </a>
                <a href="{{ route('events.past') }}" class="px-4 py-2 text-xs uppercase tracking-wider font-display font-bold rounded-sm border transition-colors {{ request()->routeIs('events.past') ? 'border-lucille-accent bg-lucille-accent/15 text-white' : 'border-white/10 text-[#7b7b7b] hover:text-white hover:border-white/30 bg-black/30' }}">
                    Eventos pasados
                </a>
            </nav>

            {{-- Agenda de eventos integrada directamente en el fondo continuo con textura Lucille --}}
            <div class="lucille-events-container max-w-[1200px] mx-auto px-4 sm:px-6">
                <x-ui.event-list :events="$events" :emphasize-first="true" />
            </div>
        </div>
    </section>
</x-layouts.site>
