<x-layouts.site title="Personas Desaparecidas - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}" :showPlayer="false" :showSocialFlyout="false">
    @push('preloads')
        <meta http-equiv="refresh" content="30">
    @endpush
    <x-sections.page-heading title="Personas Desaparecidas">
        <p class="text-base md:text-lg font-normal tracking-wide text-white/80 not-italic mt-2 uppercase">
            Ayúdanos a encontrar a quienes faltan en casa. Comparte información o reporta un caso.
        </p>
    </x-sections.page-heading>

    <section class="lucille-section bg-lucille-surface">
        <div class="lucille-container">
            <!-- Estadísticas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <!-- Personas Reportadas -->
                <div class="bg-[#121212] border border-white/5 rounded-xl p-6 flex items-center shadow-lg transition-transform hover:-translate-y-1 duration-300">
                    <div class="p-4 bg-lucille-accent/10 rounded-full mr-5 text-lucille-accent">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs text-lucille-text-muted font-bold uppercase tracking-wider mb-1">Personas Reportadas</h4>
                        <p class="text-4xl font-display font-bold text-white">{{ $stats['reportadas'] }}</p>
                    </div>
                </div>

                <!-- En Búsqueda -->
                <div class="bg-[#121212] border border-white/5 rounded-xl p-6 flex items-center shadow-lg transition-transform hover:-translate-y-1 duration-300">
                    <div class="p-4 bg-yellow-500/10 rounded-full mr-5 text-yellow-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs text-lucille-text-muted font-bold uppercase tracking-wider mb-1">En Búsqueda</h4>
                        <p class="text-4xl font-display font-bold text-white">{{ $stats['buscadas'] }}</p>
                    </div>
                </div>

                <!-- Personas Encontradas -->
                <div class="bg-[#121212] border border-white/5 rounded-xl p-6 flex items-center shadow-lg transition-transform hover:-translate-y-1 duration-300">
                    <div class="p-4 bg-green-500/10 rounded-full mr-5 text-green-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs text-lucille-text-muted font-bold uppercase tracking-wider mb-1">Personas Encontradas</h4>
                        <p class="text-4xl font-display font-bold text-white">{{ $stats['encontradas'] }}</p>
                    </div>
                </div>
            </div>

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
                <div x-data="{ lightboxOpen: false, lightboxImage: '' }" class="relative">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        @foreach($missingPersons as $person)
                            <div class="bg-[#121212] rounded-xl overflow-hidden shadow-2xl border border-white/5 flex flex-row group relative">
                                <!-- Información -->
                                <div class="p-5 flex-1 flex flex-col justify-center">
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

                                        @if($person->hospital_admitted_to)
                                        <div class="flex items-start">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-lucille-accent/80 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                            <span><strong class="text-white">Hospital:</strong> {{ $person->hospital_admitted_to }}</span>
                                        </div>
                                        @endif

                                        @if($person->service_provided)
                                        <div class="flex items-start">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-lucille-accent/80 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                            <span><strong class="text-white">Servicio:</strong> {{ $person->service_provided }}</span>
                                        </div>
                                        @endif

                                        @if($person->date_update)
                                        <div class="flex items-start">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-lucille-accent/80 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            <span><strong class="text-white">Actualizado:</strong> {{ $person->date_update->format('d/m/Y') }}</span>
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

                                <!-- Foto (Derecha) -->
                                <div class="relative w-1/3 aspect-[3/4] overflow-hidden bg-[#0a0a0a] shrink-0 border-l border-white/5 {{ $person->photo_url ? 'cursor-pointer' : '' }}" 
                                     @if($person->photo_url) @click="lightboxOpen = true; lightboxImage = '{{ $person->photo_url }}'" @endif>
                                    @if($person->photo_url)
                                        <img src="{{ $person->photo_url }}" alt="Foto de {{ $person->full_name }}" class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105">
                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                            </svg>
                                        </div>
                                    @else
                                        <div class="absolute inset-0 flex h-full w-full items-center justify-center text-white/20">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="absolute top-2 right-2 bg-red-600 text-white text-[9px] font-bold uppercase tracking-wider px-2 py-1 rounded shadow-lg">
                                        Desaparecido
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="mt-12">
                        {{ $missingPersons->links('vendor.pagination.tailwind') }}
                    </div>

                    <!-- Lightbox Modal -->
                    <div x-show="lightboxOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" @click="lightboxOpen = false" @keydown.escape.window="lightboxOpen = false">
                        <div class="relative max-w-4xl max-h-[90vh]">
                            <button @click="lightboxOpen = false" class="absolute -top-12 right-0 text-white hover:text-lucille-accent transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <img :src="lightboxImage" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" @click.stop>
                        </div>
                    </div>
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
