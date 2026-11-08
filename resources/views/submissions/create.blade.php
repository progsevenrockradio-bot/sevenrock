<x-layouts.site title="Seven Rock Radio - Enviar Maqueta" description="Envía tu maqueta a Seven Rock Radio para que nuestro equipo de A&R la escuche." ogImage="{{ asset('images/tu-imagen-para-maquetas.png') }}">
    <x-sections.page-heading title="Enviar Maqueta" overlay="rgba(0,0,0,0)" :image="null" />

    <section>
        <div class="lucille-content-box">
            <div class="grid gap-12 lg:grid-cols-2">
                <div class="md:pr-[15px]">
                    <h3 class="mb-10 mt-[30px] font-display text-[16px] font-light tracking-[.04em] text-[#dcdcdc]">Envía tu material a nuestro equipo</h3>
                    
                    @if(session('success'))
                        <div style="background: rgba(0,255,0,0.1); border: 1px solid #0f0; color: #0f0; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div style="background: rgba(255,0,0,0.1); border: 1px solid #f00; color: #f00; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                            <ul style="margin: 0; padding-left: 20px;">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        
                        <input type="text" name="band_name" placeholder="Nombre de la Banda o Artista *" value="{{ old('band_name') }}" class="lucille-form-field w-full" required>
                        <input type="text" name="song_title" placeholder="Título de la Canción *" value="{{ old('song_title') }}" class="lucille-form-field w-full" required>
                        <input type="email" name="contact_email" placeholder="Correo Electrónico de Contacto *" value="{{ old('contact_email') }}" class="lucille-form-field w-full" required>
                        <input type="url" name="social_link" placeholder="Enlace a Instagram, Spotify o Web (Opcional)" value="{{ old('social_link') }}" class="lucille-form-field w-full">
                        
                        <div class="pt-2">
                            <label class="block text-[#7b7b7b] mb-2 text-sm">Archivo de Audio (MP3, WAV, FLAC - Máx. 50MB) *</label>
                            <input type="file" name="audio_file" accept=".mp3,.wav,.flac" class="lucille-form-field w-full" style="padding-top: 10px;" required>
                        </div>

                        <div class="pt-3 border-t border-white/10">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-[#7b7b7b]">Comunicaciones opcionales (no obligatorias)</p>
                            <label for="marketing_opt_in" class="flex items-start gap-3 cursor-pointer rounded-md border border-white/10 bg-[#101012] p-3.5 text-sm text-[#dcdcdc]">
                                <input
                                    id="marketing_opt_in"
                                    type="checkbox"
                                    name="marketing_opt_in"
                                    value="1"
                                    @checked(old('marketing_opt_in') === '1')
                                    class="mt-1 h-4 w-4 shrink-0 accent-[#c32720]"
                                >
                                <span class="leading-relaxed">
                                    Quiero recibir información sobre los servicios y planes del Muro del Rock. Puedo darme de baja cuando quiera.
                                    <span class="mt-1 block text-xs text-[#7b7b7b]">
                                        Opcional. Enviar, evaluar o publicar tu maqueta no depende de marcar esta casilla.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="pt-4">
                            <button type="submit" class="lucille-button-solid w-full">Enviar Maqueta a A&R</button>
                        </div>
                    </form>
                </div>

                <div class="md:pl-[15px]">
                    <h3 class="mb-10 mt-[30px] font-display text-[16px] font-light tracking-[.04em] text-[#dcdcdc]">Información Importante</h3>
                    <div class="space-y-5 text-[15px] leading-7 text-[#7b7b7b]">
                        <p>En Seven Rock Radio siempre estamos buscando nuevos talentos para incorporar a nuestra programación diaria.</p>
                        <p>Nuestro equipo de A&R (Artistas y Repertorio) escucha todas las propuestas que recibimos a través de este formulario.</p>
                        <p><strong>¿Qué debes tener en cuenta?</strong></p>
                        <ul style="list-style: disc; padding-left: 20px;" class="space-y-2">
                            <li>Asegúrate de que el archivo de audio tenga la mejor calidad posible.</li>
                            <li>Solo aceptamos formatos estándar como MP3, WAV o FLAC.</li>
                            <li>Si tu canción encaja en nuestra parrilla, te contactaremos al correo que nos proporciones.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
