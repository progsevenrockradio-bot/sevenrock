<x-layouts.agency :title="'Agencia - Configuración de Perfil'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3] rounded-[8px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-8 rounded-[8px] shadow-xl">
            <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Configuración de Agencia</h1>
            <p class="mt-2 text-sm text-[#7b7b7b]">Actualiza la información de tu cuenta, tu contraseña y tu logo oficial.</p>

            <form action="{{ route('agency.profile.update') }}" method="POST" enctype="multipart/form-data" class="mt-8 space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre de la Agencia</label>
                        <input type="text" name="name" value="{{ old('name', $agency->name) }}" class="lucille-product-field w-full" required>
                        @error('name')
                            <span class="mt-1 block text-xs text-red-400 font-mono uppercase">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo Electrónico</label>
                        <input type="email" name="email" value="{{ old('email', $agency->email) }}" class="lucille-product-field w-full" required>
                        @error('email')
                            <span class="mt-1 block text-xs text-red-400 font-mono uppercase">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Sitio Web Oficial (URL)</label>
                    <input type="url" name="website_url" value="{{ old('website_url', $agency->website_url) }}" placeholder="https://miagencia.com" class="lucille-product-field w-full">
                    @error('website_url')
                        <span class="mt-1 block text-xs text-red-400 font-mono uppercase">{{ $message }}</span>
                    @enderror
                </div>

                <div class="border-t border-white/5 pt-6">
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white mb-4">Logotipo Oficial</h3>
                    <div class="grid gap-4 md:grid-cols-[150px_1fr] items-center">
                        <div class="border border-white/10 p-2 bg-black/45 rounded-[6px] h-28 flex items-center justify-center overflow-hidden">
                            @if($agency->logo_path)
                                <img src="{{ $agency->logo_url }}" alt="{{ $agency->name }}" class="max-h-full max-w-full object-contain" loading="lazy">
                            @else
                                <span class="text-[10px] text-gray-500 uppercase tracking-widest text-center">Sin Logo</span>
                            @endif
                        </div>
                        <div>
                            <input type="file" name="logo_file" class="text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-[var(--lucille-accent)]/10 file:text-[var(--lucille-accent)] hover:file:bg-[var(--lucille-accent)]/20 cursor-pointer">
                            <p class="text-[10px] text-gray-500 mt-2">Formatos aceptados: PNG, JPG, JPEG, WEBP. Tamaño máximo: 4MB.</p>
                            @error('logo_file')
                                <span class="mt-1 block text-xs text-red-400 font-mono uppercase">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/5 pt-6 space-y-6">
                    <div>
                        <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">Cambiar Contraseña</h3>
                        <p class="text-xs text-[#7b7b7b] mt-1">Completa estos campos únicamente si deseas actualizar tu contraseña actual.</p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nueva Contraseña</label>
                            <input type="password" name="password" class="lucille-product-field w-full">
                            @error('password')
                                <span class="mt-1 block text-xs text-red-400 font-mono uppercase">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Confirmar Contraseña</label>
                            <input type="password" name="password_confirmation" class="lucille-product-field w-full">
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/5 pt-6 text-right">
                    <button type="submit" class="lucille-button-solid">
                        Guardar Perfil
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.agency>
