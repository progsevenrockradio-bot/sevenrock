<x-layouts.admin :title="'Enviar Media Kit / Datos de Radio - '.$themeSettings->site_name">
    <div class="mb-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Enviar Información de la Radio</h1>
        <p class="mt-2 text-sm text-[#8a8a8a]">Usa este formulario para enviar el logo, las redes sociales y el Media Kit en PDF de Seven Rock Radio a bandas, patrocinadores o agencias.</p>
    </div>

    @if(session('status'))
        <div class="mb-6 border border-[#2b2b2b] bg-[linear-gradient(135deg,rgba(16,16,18,0.96),rgba(20,50,20,0.4))] p-4 rounded-lg relative overflow-hidden">
            <p class="text-sm text-green-400">{{ session('status') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 border border-[#2b2b2b] bg-[linear-gradient(135deg,rgba(16,16,18,0.96),rgba(50,20,20,0.4))] p-4 rounded-lg relative overflow-hidden">
            <p class="text-sm text-red-400">{{ session('error') }}</p>
        </div>
    @endif

    <div class="border border-[#2b2b2b] bg-[linear-gradient(135deg,rgba(16,16,18,0.96),rgba(20,10,30,0.4))] p-8 rounded-lg relative overflow-hidden">
        <form action="{{ route('admin.media-kit.send') }}" method="POST" class="space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                <div>
                    <label for="recipient_email" class="block text-xs font-semibold uppercase tracking-wider text-[#a0a0a0] mb-2">Correo del Destinatario *</label>
                    <input type="email" name="recipient_email" id="recipient_email" required
                        class="w-full bg-[rgba(0,0,0,0.2)] border border-[#3b3b3b] rounded p-2.5 text-sm text-white focus:outline-none focus:border-[var(--lucille-accent)]"
                        placeholder="contacto@banda.com" value="{{ old('recipient_email') }}">
                    @error('recipient_email')
                        <p class="mt-2 text-xs text-[var(--lucille-accent)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="recipient_name" class="block text-xs font-semibold uppercase tracking-wider text-[#a0a0a0] mb-2">Nombre del Destinatario (Opcional)</label>
                    <input type="text" name="recipient_name" id="recipient_name" 
                        class="w-full bg-[rgba(0,0,0,0.2)] border border-[#3b3b3b] rounded p-2.5 text-sm text-white focus:outline-none focus:border-[var(--lucille-accent)]"
                        placeholder="Ej. Juan Pérez" value="{{ old('recipient_name') }}">
                    @error('recipient_name')
                        <p class="mt-2 text-xs text-[var(--lucille-accent)]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="subject" class="block text-xs font-semibold uppercase tracking-wider text-[#a0a0a0] mb-2">Asunto *</label>
                    <input type="text" name="subject" id="subject" required
                        class="w-full bg-[rgba(0,0,0,0.2)] border border-[#3b3b3b] rounded p-2.5 text-sm text-white focus:outline-none focus:border-[var(--lucille-accent)]"
                        value="{{ old('subject', 'Media Kit y Datos Oficiales - Seven Rock Radio') }}">
                    @error('subject')
                        <p class="mt-2 text-xs text-[var(--lucille-accent)]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="custom_message" class="block text-xs font-semibold uppercase tracking-wider text-[#a0a0a0] mb-2">Mensaje Personalizado (Opcional)</label>
                    <textarea id="custom_message" name="custom_message" rows="4" 
                        class="w-full bg-[rgba(0,0,0,0.2)] border border-[#3b3b3b] rounded p-2.5 text-sm text-white focus:outline-none focus:border-[var(--lucille-accent)]"
                        placeholder="Hola equipo, les comparto nuestro media kit y recursos gráficos...">{{ old('custom_message') }}</textarea>
                    <p class="mt-2 text-[10px] text-[#7b7b7b]">Este mensaje aparecerá arriba de los datos y el PDF en el correo.</p>
                    @error('custom_message')
                        <p class="mt-2 text-xs text-[var(--lucille-accent)]">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="pt-5 border-t border-[#2b2b2b] text-right">
                <button type="submit" class="lucille-button py-2 px-6 text-sm uppercase tracking-wider text-white">
                    Enviar Datos de Radio
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
