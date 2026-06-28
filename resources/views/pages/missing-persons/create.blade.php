<x-layouts.site title="Reportar Persona Desaparecida - {{ $themeSettings->site_name }}">
    <x-sections.page-heading 
        title="Reportar Caso" 
        subtitle="Por favor, completa la mayor cantidad de información posible. Tu reporte será verificado antes de ser publicado." 
    />

    <section class="lucille-section bg-lucille-surface">
        <div class="lucille-container max-w-3xl">
            
            @if(session('success'))
                <div class="bg-green-600/20 border border-green-500 text-green-100 px-6 py-4 rounded-xl mb-8 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            <form action="{{ route('missing-persons.store') }}" method="POST" enctype="multipart/form-data" class="bg-[#121212] border border-white/5 p-8 md:p-12 rounded-2xl shadow-2xl">
                @csrf
                
                <h3 class="text-2xl font-display font-bold text-white uppercase tracking-widest mb-8 border-b border-white/10 pb-4">
                    Datos de la Persona
                </h3>

                <div class="space-y-6">
                    <!-- Nombre Completo -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Nombre completo <span class="text-lucille-accent">*</span></label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required class="lucille-field w-full" placeholder="Ej. Juan Pérez">
                        @error('full_name')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Cédula -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Cédula</label>
                            <input type="text" name="cedula" value="{{ old('cedula') }}" class="lucille-field w-full" placeholder="Ej. V-12345678">
                            @error('cedula')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        <!-- Edad -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Edad (años)</label>
                            <input type="number" name="age" value="{{ old('age') }}" min="0" max="150" class="lucille-field w-full" placeholder="Ej. 35">
                            @error('age')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Sexo -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Sexo</label>
                            <select name="sex" class="lucille-field w-full bg-[#1a1a1a]">
                                <option value="">Seleccionar...</option>
                                <option value="masculino" {{ old('sex') == 'masculino' ? 'selected' : '' }}>Masculino</option>
                                <option value="femenino" {{ old('sex') == 'femenino' ? 'selected' : '' }}>Femenino</option>
                                <option value="otro" {{ old('sex') == 'otro' ? 'selected' : '' }}>Otro</option>
                            </select>
                            @error('sex')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        <!-- Fecha de desaparición -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Fecha de desaparición</label>
                            <input type="date" name="missing_since" value="{{ old('missing_since') }}" class="lucille-field w-full bg-[#1a1a1a]">
                            @error('missing_since')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <!-- Lugar de residencia -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Lugar de Residencia</label>
                        <input type="text" name="place_of_residence" value="{{ old('place_of_residence') }}" class="lucille-field w-full" placeholder="Ej. Caracas, Distrito Capital">
                        @error('place_of_residence')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- Última vez visto -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Última ubicación conocida</label>
                        <input type="text" name="last_seen_location" value="{{ old('last_seen_location') }}" class="lucille-field w-full" placeholder="Ej. Av. Francisco de Miranda, cerca del metro">
                        @error('last_seen_location')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <h3 class="text-2xl font-display font-bold text-white uppercase tracking-widest mb-8 border-b border-white/10 pb-4 mt-12">
                        Detalles Adicionales
                    </h3>

                    <!-- Descripción -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Descripción física, ropa o detalles relevantes</label>
                        <textarea name="description" rows="4" class="lucille-field w-full resize-none" placeholder="Contextura, altura, color de cabello, marcas particulares, ropa que llevaba, condiciones médicas...">{{ old('description') }}</textarea>
                        @error('description')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- Contacto de emergencia -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Número de Contacto / Emergencia <span class="text-lucille-accent">*</span></label>
                        <input type="text" name="emergency_contact_number" value="{{ old('emergency_contact_number') }}" required class="lucille-field w-full border-lucille-accent/50 focus:border-lucille-accent" placeholder="Teléfonos de familiares a contactar si es visto">
                        <p class="text-[#7b7b7b] text-xs mt-2">Este número será público para que cualquier persona con información pueda contactar directamente.</p>
                        @error('emergency_contact_number')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <!-- Foto -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Fotografía Reciente</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg" class="lucille-field w-full file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-lucille-accent file:text-white hover:file:bg-lucille-accent/90">
                        <p class="text-[#7b7b7b] text-xs mt-2">Formatos aceptados: JPG, PNG. Tamaño máximo: 2MB.</p>
                        @error('photo')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-12 flex items-center justify-between">
                    <a href="{{ route('missing-persons.index') }}" class="text-lucille-text-muted hover:text-white transition-colors">Cancelar</a>
                    <button type="submit" class="lucille-button-solid bg-lucille-accent hover:bg-lucille-accent/90 py-4 px-8 text-lg">
                        Enviar Reporte
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
