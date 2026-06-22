<x-layouts.site :title="'Actualizar: ' . $program->name" :showPlayer="false" description="Formulario de actualización de programa para Seven Rock Radio.">
    @push('styles')
        <style>
            /* Deshabilitar navegación superior */
            .lucille-nav {
                pointer-events: none !important;
                opacity: 0.3 !important;
            }
            .lucille-mobile-menu-btn {
                display: none !important;
            }
        </style>
    @endpush

    <x-sections.page-heading title="Actualizar Información del Programa" overlay="rgba(0,0,0,0)" :image="$themeAppearance['background_url'] ?? null" />

    <section>
        <div class="lucille-content-box">
            <div class="mx-auto max-w-3xl">
                @if ($errors->any())
                    <div class="mb-6 border border-[#5d2b2b] bg-[rgba(92,35,35,.28)] px-4 py-3 text-sm text-[#ffd0d0]">
                        <div class="font-bold uppercase tracking-wider mb-2">Por favor corrige los siguientes errores:</div>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-8 p-6 bg-[#1a1a1e] border border-[#2b2b2b]">
                    <h3 class="font-display text-2xl uppercase tracking-[.1em] text-[#dcdcdc] mb-2">{{ $program->name }}</h3>
                    <p class="text-[#7b7b7b] text-sm">Por favor completa o actualiza la siguiente información para mantener al día el perfil del programa en Seven Rock Radio. Este enlace es único y seguro.</p>
                </div>

                <form method="POST" action="{{ route('invitation.program.update', $invitation) }}" class="space-y-6 bg-[rgba(16,16,18,.88)] border border-[#2b2b2b] p-6 sm:p-10">
                    @csrf
                    
                    @if(in_array('nombre', $requestedFields))
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre del Programa <span class="text-red-500">*</span></label>
                            <input type="text" name="nombre" value="{{ old('nombre', $program->nombre) }}" class="lucille-form-field w-full" required>
                        </div>
                    @endif

                    @if(in_array('conductor', $requestedFields))
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Conductor / Locutor</label>
                            <input type="text" name="conductor" value="{{ old('conductor', $program->conductor) }}" class="lucille-form-field w-full" placeholder="Ej: Juan Pérez">
                        </div>
                    @endif

                    @if(in_array('genero', $requestedFields))
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Género Musical</label>
                            <input type="text" name="genero" value="{{ old('genero', $program->genero) }}" class="lucille-form-field w-full" placeholder="Ej: Heavy Metal, Rock Alternativo">
                        </div>
                    @endif

                    @if(in_array('descripcion', $requestedFields))
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción / Reseña del programa</label>
                            <textarea name="descripcion" rows="6" class="lucille-form-field w-full">{{ old('descripcion', $program->descripcion) }}</textarea>
                        </div>
                    @endif

                    <div class="grid gap-6 sm:grid-cols-2">
                        @if(in_array('dia_transmision', $requestedFields))
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Día de Transmisión</label>
                                <input type="text" name="dia_transmision" value="{{ old('dia_transmision', $program->dia_transmision) }}" class="lucille-form-field w-full" placeholder="Ej: Lunes, Jueves y Viernes">
                            </div>
                        @endif

                        @if(in_array('hora_transmision', $requestedFields))
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Hora de Transmisión</label>
                                <input type="text" name="hora_transmision" value="{{ old('hora_transmision', $program->hora_transmision) }}" class="lucille-form-field w-full" placeholder="Ej: 20:00">
                            </div>
                        @endif
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        @if(in_array('red_social1_url', $requestedFields))
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Red Social 1 (URL)</label>
                                <input type="url" name="red_social1_url" value="{{ old('red_social1_url', $program->red_social1_url) }}" class="lucille-form-field w-full" placeholder="https://instagram.com/...">
                            </div>
                        @endif

                        @if(in_array('red_social2_url', $requestedFields))
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Red Social 2 (URL)</label>
                                <input type="url" name="red_social2_url" value="{{ old('red_social2_url', $program->red_social2_url) }}" class="lucille-form-field w-full" placeholder="https://facebook.com/...">
                            </div>
                        @endif
                    </div>

                    @if(in_array('caratula_url', $requestedFields))
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">URL de la Carátula / Imagen</label>
                            <input type="url" name="caratula_url" value="{{ old('caratula_url', $program->caratula_url) }}" class="lucille-form-field w-full" placeholder="Enlace a una imagen .jpg o .png">
                            <p class="mt-2 text-xs text-[#7b7b7b]">Puedes subir tu imagen a un sitio gratuito como postimages.org y pegar aquí el "enlace directo".</p>
                        </div>
                    @endif

                    <div class="pt-6 border-t border-[#2b2b2b]">
                        <button type="submit" class="lucille-button-solid w-full text-center py-4 bg-[#a855f7] hover:bg-[#9333ea] border-none text-white">
                            Guardar Información
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</x-layouts.site>
