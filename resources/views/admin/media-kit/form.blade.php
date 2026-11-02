<x-layouts.admin :title="'Dossier comercial (Media Kit) - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php
        $configuredSocials = collect($socialLinks ?? [])->filter(function ($social) {
            return !empty($social['network']) && !empty(trim((string) ($social['url'] ?? '')));
        })->values();

        $unconfiguredSocials = collect($socialLinks ?? [])->filter(function ($social) {
            return !empty($social['network']) && empty(trim((string) ($social['url'] ?? '')));
        })->pluck('network')->values();

        $customLogoUrl = $themeSettings->logo_url ?? null;
        $defaultLogoExists = file_exists(public_path('assets/lucille/logo.png'));
        $logoAvailable = !empty($customLogoUrl) || $defaultLogoExists;
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Comunicación · Presentación institucional</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Dossier comercial (Media Kit)</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Envía por correo la presentación oficial de Seven Rock Radio a bandas, promotores, agencias o patrocinadores, con generación automática de ficha en PDF.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.email-logs.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    <span>Historial de correos</span>
                </a>
                <a href="{{ route('admin.direct-email.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    <span>Redactar correo libre</span>
                </a>
            </div>
        </header>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('admin.media-kit.send') }}" method="POST" class="grid gap-5 lg:grid-cols-[1.25fr_.75fr] items-start">
            @csrf

            {{-- Columna principal: Destinatario, mensaje y selección de recursos --}}
            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
                <div class="space-y-4">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white border-b border-white/5 pb-2.5">1. Destinatario y mensaje de presentación</h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="recipient_email" class="mb-1.5 block text-xs font-medium text-gray-300">
                                Correo del destinatario <span class="text-[#c32720]">*</span>
                            </label>
                            <input
                                type="email"
                                name="recipient_email"
                                id="recipient_email"
                                required
                                placeholder="contacto@banda.com"
                                value="{{ old('recipient_email') }}"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            >
                            @error('recipient_email')
                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="recipient_name" class="mb-1.5 block text-xs font-medium text-gray-300">
                                Nombre o entidad destinataria (opcional)
                            </label>
                            <input
                                type="text"
                                name="recipient_name"
                                id="recipient_name"
                                placeholder="Ej: Agencia Rock / Nombre del contacto"
                                value="{{ old('recipient_name') }}"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            >
                            @error('recipient_name')
                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="subject" class="mb-1.5 block text-xs font-medium text-gray-300">
                                Asunto del correo <span class="text-[#c32720]">*</span>
                            </label>
                            <input
                                type="text"
                                name="subject"
                                id="subject"
                                required
                                value="{{ old('subject', 'Media Kit y Datos Oficiales - Seven Rock Radio') }}"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            >
                            @error('subject')
                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="custom_message" class="mb-1.5 block text-xs font-medium text-gray-300">
                                Nota introductoria personalizada (opcional)
                            </label>
                            <textarea
                                id="custom_message"
                                name="custom_message"
                                rows="4"
                                placeholder="Hola equipo, les compartimos nuestra presentación oficial y datos de contacto..."
                                class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            >{{ old('custom_message') }}</textarea>
                            <p class="mt-1 text-[11px] text-gray-400">Si se indica, se mostrará destacada al inicio del cuerpo del correo antes de la reseña institucional.</p>
                            @error('custom_message')
                                <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Recursos incluidos --}}
                <div class="space-y-3 border-t border-white/5 pt-4">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">2. Recursos incluidos en el correo y en el PDF</h2>

                    <label class="flex items-start gap-2.5 rounded-lg border border-white/10 bg-[#151518] p-3 text-xs cursor-pointer">
                        <input
                            type="checkbox"
                            name="include_logo"
                            value="1"
                            @checked(old('include_logo', true))
                            class="mt-0.5 h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]"
                        >
                        <div>
                            <div class="font-semibold text-white">Incluir logotipo oficial de la emisora</div>
                            <div class="mt-0.5 text-[11px] text-gray-400">
                                @if ($logoAvailable)
                                    Muestra el logotipo en la cabecera del correo y del PDF, y adjunta el archivo <code class="text-gray-300">Logo_Oficial.png</code>.
                                @else
                                    No se detectó un archivo de logotipo local; se mostrará el nombre de la emisora en texto.
                                @endif
                            </div>
                        </div>
                    </label>

                    <div class="rounded-lg border border-white/10 bg-[#151518] p-3.5 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-white">Enlaces a redes sociales con URL configurada ({{ $configuredSocials->count() }})</span>
                            <span class="text-[11px] text-gray-400">Solo se envían las redes marcadas</span>
                        </div>

                        @if($configuredSocials->isNotEmpty())
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach($configuredSocials as $social)
                                    <label class="flex items-start gap-2.5 rounded-lg border border-white/5 bg-[#101012] p-2.5 text-xs cursor-pointer hover:border-white/15">
                                        <input
                                            type="checkbox"
                                            name="socials[]"
                                            value="{{ $social['network'] }}"
                                            @checked(in_array($social['network'], (array) old('socials', $configuredSocials->pluck('network')->all()), true))
                                            class="mt-0.5 h-4 w-4 rounded border-white/20 bg-[#151518] text-[#c32720] focus:ring-[#c32720]"
                                        >
                                        <div class="min-w-0">
                                            <div class="font-medium text-white capitalize">{{ $social['network'] }}</div>
                                            <div class="font-mono text-[10px] text-gray-400 truncate" title="{{ $social['url'] }}">{{ $social['url'] }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-amber-200/90">
                                Actualmente no hay redes sociales con URL activa en Configuración. El correo y el PDF se enviarán sin bloque de redes sociales.
                            </p>
                        @endif

                        @if($unconfiguredSocials->isNotEmpty())
                            <p class="text-[11px] text-gray-400 border-t border-white/5 pt-2">
                                Redes omitidas por no tener URL configurada: <span class="text-gray-300">{{ $unconfiguredSocials->map(fn ($n) => ucfirst($n))->implode(', ') }}</span>.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end border-t border-white/5 pt-3">
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Enviar dossier comercial
                    </button>
                </div>
            </div>

            {{-- Columna lateral: Detalle transparente de qué se adjunta realmente --}}
            <aside class="space-y-4">
                <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3 text-xs">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white border-b border-white/5 pb-2">¿Qué incluye exactamente este envío?</h2>

                    <div class="space-y-2.5 text-gray-300 leading-relaxed">
                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                            <div class="font-semibold text-white">1. PDF autogenerado al instante</div>
                            <div class="mt-0.5 font-mono text-[11px] text-emerald-300">Media_Kit_Seven_Rock_Radio.pdf</div>
                            <p class="mt-1 text-[11px] text-gray-400">
                                Se genera dinámicamente con <code class="text-gray-300">Dompdf</code> (plantilla <code class="text-gray-300">resources/views/pdf/media-kit.blade.php</code>) incluyendo la reseña oficial de la radio y únicamente las redes con URL válida que marques en el formulario. No depende de ningún PDF externo subido manualmente.
                            </p>
                        </div>

                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                            <div class="font-semibold text-white">2. Archivo de logotipo (opcional)</div>
                            <div class="mt-0.5 font-mono text-[11px] {{ $logoAvailable ? 'text-emerald-300' : 'text-amber-300' }}">
                                {{ $logoAvailable ? 'Logo_Oficial.png (disponible)' : 'Archivo de logo no detectado' }}
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">
                                Si mantienes activa la casilla «Incluir logotipo oficial», se adjunta la imagen del logotipo como archivo descargable además de mostrarse en la cabecera.
                            </p>
                        </div>

                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                            <div class="font-semibold text-white">3. Reseña institucional incluida</div>
                            <p class="mt-1 text-[11px] text-gray-400 italic">
                                «{{ $defaultDescription }}»
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    </div>
</x-layouts.admin>
