<x-layouts.site title="Personas Desaparecidas - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}" :showPlayer="false">
    <x-sections.page-heading title="Personas Desaparecidas">
        <p class="text-base md:text-lg font-normal tracking-wide text-white/80 not-italic mt-2 uppercase">
            Ayúdanos a encontrar a quienes faltan en casa. Comparte información o reporta un caso.
        </p>
    </x-sections.page-heading>

    <section class="lucille-section bg-lucille-surface">
        <div class="lucille-container">
            <!-- Buscador y Acciones -->
            <div class="mb-12 flex flex-col md:flex-row items-center justify-between gap-6">
                <form action="{{ route('missing-persons.index') }}" method="GET" class="w-full md:max-w-md relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Buscar por nombre, cédula o lugar..." 
                        class="lucille-field w-full pr-12"
                    >
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-lucille-text-muted hover:text-lucille-accent transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </form>

                <a href="{{ route('missing-persons.create') }}" class="lucille-button-solid whitespace-nowrap bg-lucille-accent hover:bg-lucille-accent/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Reportar Caso
                </a>
            </div>

            <!-- Listado -->
            @if($missingPersons->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-6">
                    @foreach($missingPersons as $person)
                        <div class="bg-[#121212] rounded-xl overflow-hidden shadow-2xl border border-white/5 flex flex-col group relative">
                            <!-- Foto -->
                            <div class="relative w-full aspect-[3/4] overflow-hidden bg-[#0a0a0a]">
                                @if($person->photo_url)
                                    <img src="{{ $person->photo_url }}" alt="Foto de {{ $person->full_name }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105">
                                @else
                                    <div class="absolute inset-0 flex h-full w-full items-center justify-center text-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="absolute top-3 left-3 bg-red-600 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full shadow-lg">
                                    Desaparecido
                                </div>
                            </div>
                            
                            <!-- Información -->
                            <div class="p-5 flex-1 flex flex-col">
                                <h3 class="text-lg font-display font-bold text-white mb-2 uppercase tracking-wide">{{ $person->full_name }}</h3>
                                
                                <div class="space-y-2 text-xs text-lucille-text-muted mb-5 flex-1">
                                    @if($person->age)
                                    <div class="flex items-start">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-lucille-accent/80 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        <span><strong class="text-white">Edad:</strong> {{ $person->age }} años ({{ ucfirst($person->sex) }})</span>
                                    </div>
                                    @endif
                                    
                                    @if($person->missing_since)
                                    <div class="flex items-start">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-lucille-accent/80 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <span><strong class="text-white">Desde:</strong> {{ $person->missing_since->format('d/m/Y') }}</span>
                                    </div>
                                    @endif
                                    
                                    @if($person->last_seen_location)
                                    <div class="flex items-start">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-lucille-accent/80 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <span><strong class="text-white">Última vez visto:</strong> {{ $person->last_seen_location }}</span>
                                    </div>
                                    @endif
                                </div>

                                @if($person->emergency_contact_number)
                                <div class="bg-lucille-accent/10 border border-lucille-accent/20 rounded-lg p-4 mt-auto">
                                    <p class="text-xs text-lucille-accent font-bold uppercase tracking-widest mb-1">Contacto de Emergencia</p>
                                    <p class="text-white font-mono text-lg">{{ $person->emergency_contact_number }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-12">
                    {{ $missingPersons->links('vendor.pagination.tailwind') }}
                </div>
            @else
                <div class="bg-[#121212] border border-white/5 rounded-2xl p-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-white/20 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h3 class="text-2xl font-display font-bold text-white mb-3">No hay casos reportados</h3>
                    <p class="text-lucille-text-muted max-w-lg mx-auto">Actualmente no hay personas desaparecidas activas que coincidan con tu búsqueda en nuestra base de datos.</p>
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
