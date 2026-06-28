<x-layouts.site title="Moderación de Personas Desaparecidas - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}" :showPlayer="false" :showSocialFlyout="false">
    <x-sections.page-heading title="Moderación">
        <p class="text-base md:text-lg font-normal tracking-wide text-white/80 not-italic mt-2 uppercase">
            Panel in-site para gestionar reportes de personas desaparecidas.
        </p>
    </x-sections.page-heading>

    <section class="lucille-section bg-lucille-surface">
        <div class="lucille-container max-w-6xl">
            
            @if(session('success'))
                <div class="bg-green-600/20 border border-green-500 text-green-100 px-6 py-4 rounded-xl mb-8 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            <!-- Filtros rápidos -->
            <div class="mb-8 flex flex-wrap gap-4 items-center bg-[#121212] p-4 rounded-xl border border-white/5">
                <span class="text-xs font-bold uppercase tracking-widest text-[#7b7b7b] mr-2">Filtros:</span>
                <a href="{{ route('missing-persons.moderation.index') }}" class="px-4 py-2 text-sm rounded-lg {{ !request()->has('approved') && !request()->has('status') ? 'bg-lucille-accent text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">
                    Todos
                </a>
                <a href="{{ route('missing-persons.moderation.index', ['approved' => '0']) }}" class="px-4 py-2 text-sm rounded-lg {{ request('approved') === '0' ? 'bg-lucille-accent text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">
                    Pendientes de Aprobación
                </a>
                <a href="{{ route('missing-persons.moderation.index', ['status' => 'active', 'approved' => '1']) }}" class="px-4 py-2 text-sm rounded-lg {{ request('status') === 'active' && request('approved') === '1' ? 'bg-lucille-accent text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">
                    Activos y Aprobados
                </a>
                <a href="{{ route('missing-persons.moderation.index', ['status' => 'found']) }}" class="px-4 py-2 text-sm rounded-lg {{ request('status') === 'found' ? 'bg-lucille-accent text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">
                    Encontrados
                </a>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto bg-[#121212] rounded-xl border border-white/5 shadow-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/10">
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-[#7b7b7b]">Persona</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-[#7b7b7b]">Estado</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-[#7b7b7b]">Aprobado</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-[#7b7b7b]">Fecha Reporte</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-[#7b7b7b] text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($missingPersons as $person)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-4">
                                        @if($person->photo_url)
                                            <img src="{{ $person->photo_url }}" class="w-12 h-12 rounded-full object-cover border border-white/10" alt="Foto">
                                        @else
                                            <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div>
                                            <p class="text-white font-bold">{{ $person->full_name }}</p>
                                            <p class="text-xs text-lucille-text-muted">{{ $person->cedula ?? 'Sin cédula' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    @if($person->status === 'found')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/50 text-green-400 border border-green-800">Encontrado</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/50 text-red-400 border border-red-800">Activo</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    @if($person->is_approved)
                                        <span class="text-green-500"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg></span>
                                    @else
                                        <span class="text-orange-500"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" /></svg></span>
                                    @endif
                                </td>
                                <td class="p-4 text-sm text-lucille-text-muted">
                                    {{ $person->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    @if(!$person->is_approved)
                                        <form action="{{ route('missing-persons.moderation.approve', $person) }}" method="POST" class="inline-block">
                                            @csrf
                                            <button type="submit" title="Aprobar Reporte" class="p-2 bg-green-600/20 text-green-500 hover:bg-green-600 hover:text-white rounded transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </form>
                                    @endif

                                    @if($person->status === 'active')
                                        <form action="{{ route('missing-persons.moderation.found', $person) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Confirmar que esta persona ha sido encontrada?');">
                                            @csrf
                                            <button type="submit" title="Marcar como Encontrado" class="p-2 bg-blue-600/20 text-blue-500 hover:bg-blue-600 hover:text-white rounded transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M14.414 7l3.293-3.293a1 1 0 00-1.414-1.414L13 5.586V4a1 1 0 10-2 0v4.003a.996.996 0 00.617.921A.997.997 0 0012 9h4a1 1 0 100-2h-1.586zM2 6a2 2 0 012-2h2.5l2.354 2.354A2 2 0 019.268 7H11a1 1 0 110 2H9.268a2 2 0 01-.414.646L6.5 12H4a2 2 0 01-2-2V6z" /></svg>
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('missing-persons.moderation.edit', $person) }}" title="Editar" class="p-2 inline-block bg-yellow-600/20 text-yellow-500 hover:bg-yellow-600 hover:text-white rounded transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                    </a>

                                    <form action="{{ route('missing-persons.moderation.destroy', $person) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este reporte permanentemente?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar" class="p-2 bg-red-600/20 text-red-500 hover:bg-red-600 hover:text-white rounded transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-lucille-text-muted">
                                    No hay reportes que mostrar en esta vista.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-8">
                {{ $missingPersons->links('vendor.pagination.tailwind') }}
            </div>

        </div>
    </section>
</x-layouts.site>
