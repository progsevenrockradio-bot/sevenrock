<x-layouts.site title="Editar Registro - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}" :showPlayer="false" :showSocialFlyout="false">
    <x-sections.page-heading title="Editar Registro">
        <p class="text-base md:text-lg font-normal tracking-wide text-white/80 not-italic mt-2 uppercase">
            Estás editando el registro de {{ $missingPerson->full_name }}.
        </p>
    </x-sections.page-heading>

    <section class="lucille-section bg-lucille-surface">
        <div class="lucille-container max-w-3xl">
            <form action="{{ route('missing-persons.moderation.update', $missingPerson) }}" method="POST" enctype="multipart/form-data" class="bg-[#121212] border border-white/5 p-8 md:p-12 rounded-2xl shadow-2xl">
                @csrf
                @method('PUT')
                
                <h3 class="text-2xl font-display font-bold text-white uppercase tracking-widest mb-8 border-b border-white/10 pb-4">
                    Estado Administrativo
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Aprobación Pública</label>
                        <select name="is_approved" class="lucille-field w-full bg-[#1a1a1a]">
                            <option value="1" {{ old('is_approved', $missingPerson->is_approved) ? 'selected' : '' }}>Aprobado (Visible)</option>
                            <option value="0" {{ !old('is_approved', $missingPerson->is_approved) ? 'selected' : '' }}>Pendiente (Oculto)</option>
                        </select>
                        @error('is_approved')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Estado del Caso</label>
                        <select name="status" class="lucille-field w-full bg-[#1a1a1a]">
                            <option value="active" {{ old('status', $missingPerson->status) === 'active' ? 'selected' : '' }}>Activo / Desaparecido</option>
                            <option value="found" {{ old('status', $missingPerson->status) === 'found' ? 'selected' : '' }}>Encontrado</option>
                        </select>
                        @error('status')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <h3 class="text-2xl font-display font-bold text-white uppercase tracking-widest mb-8 border-b border-white/10 pb-4">
                    Datos de la Persona
                </h3>

                <div class="space-y-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Nombre completo <span class="text-lucille-accent">*</span></label>
                        <input type="text" name="full_name" value="{{ old('full_name', $missingPerson->full_name) }}" required class="lucille-field w-full" placeholder="Ej. Juan Pérez">
                        @error('full_name')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Cédula</label>
                            <input type="text" name="cedula" value="{{ old('cedula', $missingPerson->cedula) }}" class="lucille-field w-full" placeholder="Ej. V-12345678">
                            @error('cedula')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Edad (años)</label>
                            <input type="number" name="age" value="{{ old('age', $missingPerson->age) }}" min="0" max="150" class="lucille-field w-full" placeholder="Ej. 35">
                            @error('age')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Sexo</label>
                            <select name="sex" class="lucille-field w-full bg-[#1a1a1a]">
                                <option value="">Seleccionar...</option>
                                <option value="masculino" {{ old('sex', $missingPerson->sex) === 'masculino' ? 'selected' : '' }}>Masculino</option>
                                <option value="femenino" {{ old('sex', $missingPerson->sex) === 'femenino' ? 'selected' : '' }}>Femenino</option>
                                <option value="otro" {{ old('sex', $missingPerson->sex) === 'otro' ? 'selected' : '' }}>Otro</option>
                            </select>
                            @error('sex')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Fecha de desaparición</label>
                            <input type="date" name="missing_since" value="{{ old('missing_since', $missingPerson->missing_since ? $missingPerson->missing_since->format('Y-m-d') : '') }}" class="lucille-field w-full bg-[#1a1a1a]">
                            @error('missing_since')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Lugar de Residencia</label>
                        <input type="text" name="place_of_residence" value="{{ old('place_of_residence', $missingPerson->place_of_residence) }}" class="lucille-field w-full" placeholder="Ej. Caracas, Distrito Capital">
                        @error('place_of_residence')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Última ubicación conocida</label>
                        <input type="text" name="last_seen_location" value="{{ old('last_seen_location', $missingPerson->last_seen_location) }}" class="lucille-field w-full" placeholder="Ej. Av. Francisco de Miranda, cerca del metro">
                        @error('last_seen_location')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <h3 class="text-2xl font-display font-bold text-white uppercase tracking-widest mb-8 border-b border-white/10 pb-4 mt-12">
                        Datos Médicos / Hospitalarios (Opcional)
                    </h3>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Ingresado por sismo a (Hospital)</label>
                        <input type="text" name="hospital_admitted_to" value="{{ old('hospital_admitted_to', $missingPerson->hospital_admitted_to) }}" class="lucille-field w-full" placeholder="Ej. Hospital Domingo Luciani">
                        @error('hospital_admitted_to')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Servicio</label>
                            <input type="text" name="service_provided" value="{{ old('service_provided', $missingPerson->service_provided) }}" class="lucille-field w-full" placeholder="Ej. Cirugía General, Traumatología...">
                            @error('service_provided')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Fecha de Actualización</label>
                            <input type="date" name="date_update" value="{{ old('date_update', $missingPerson->date_update ? \Carbon\Carbon::parse($missingPerson->date_update)->format('Y-m-d') : '') }}" class="lucille-field w-full bg-[#1a1a1a]">
                            @error('date_update')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <h3 class="text-2xl font-display font-bold text-white uppercase tracking-widest mb-8 border-b border-white/10 pb-4 mt-12">
                        Detalles Adicionales
                    </h3>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Descripción física, ropa o detalles relevantes</label>
                        <textarea name="description" rows="4" class="lucille-field w-full resize-none" placeholder="Contextura, altura, color de cabello, marcas particulares...">{{ old('description', $missingPerson->description) }}</textarea>
                        @error('description')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Número de Contacto / Emergencia <span class="text-lucille-accent">*</span></label>
                        <input type="text" name="emergency_contact_number" value="{{ old('emergency_contact_number', $missingPerson->emergency_contact_number) }}" required class="lucille-field w-full border-lucille-accent/50 focus:border-lucille-accent">
                        @error('emergency_contact_number')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-[0.2em] text-[#7b7b7b] mb-2">Actualizar Fotografía (Opcional)</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg" class="lucille-field w-full file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-lucille-accent file:text-white hover:file:bg-lucille-accent/90">
                        @if($missingPerson->photo_url)
                            <div class="mt-4">
                                <p class="text-xs text-[#7b7b7b] mb-2">Foto Actual:</p>
                                <img src="{{ $missingPerson->photo_url }}" class="h-32 rounded-lg border border-white/10" alt="Foto Actual">
                            </div>
                        @endif
                        @error('photo')<p class="text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-12 flex items-center justify-between">
                    <a href="{{ route('missing-persons.moderation.index') }}" class="text-lucille-text-muted hover:text-white transition-colors">Volver a Moderación</a>
                    <button type="submit" class="lucille-button-solid bg-lucille-accent hover:bg-lucille-accent/90 py-4 px-8 text-lg">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
