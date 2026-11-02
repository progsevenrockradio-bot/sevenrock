<x-layouts.admin title="Redactar correo directo">
    <div class="mx-auto max-w-6xl space-y-5 font-sans" x-data="{
        templates: {{ json_encode($templates) }},
        selectedTemplateId: '',
        applyTemplate() {
            if (!this.selectedTemplateId) return;
            const found = this.templates.find(t => t.id == this.selectedTemplateId);
            if (found) {
                document.getElementById('email-subject').value = found.subject;
                document.getElementById('email-body').value = found.body;
            }
        }
    }">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Comunicación · Mensajería individual</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Redactar correo</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Envía un mensaje directo a uno o varios destinatarios mediante el servidor SMTP oficial, redactando desde cero o partiendo de una plantilla.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.email-logs.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Historial de correos</span>
                </a>
                <a href="{{ route('admin.media-kit.form') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Dossier comercial</span>
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <p class="font-semibold">Corrige los siguientes campos antes de enviar:</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.direct-email.send') }}" method="POST" class="grid gap-5 lg:grid-cols-[1.35fr_.65fr] items-start">
            @csrf

            {{-- Zona principal: Destinatario, Asunto y Mensaje --}}
            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
                <h2 class="font-display text-sm uppercase tracking-wider text-white border-b border-white/5 pb-2.5">Destinatario, asunto y mensaje</h2>

                <div>
                    <label for="email-recipients" class="mb-1.5 block text-xs font-medium text-gray-300">
                        Destinatario(s) <span class="text-[#c32720]">*</span>
                    </label>
                    <input
                        id="email-recipients"
                        name="recipients"
                        type="text"
                        value="{{ old('recipients', $defaultRecipient) }}"
                        placeholder="ejemplo@banda.com, contacto@agencia.com"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        required
                    >
                    <p class="mt-1 text-[11px] text-gray-400">Puedes introducir varias direcciones separadas por coma (,).</p>
                    @error('recipients')
                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email-subject" class="mb-1.5 block text-xs font-medium text-gray-300">
                        Asunto del correo <span class="text-[#c32720]">*</span>
                    </label>
                    <input
                        id="email-subject"
                        name="subject"
                        type="text"
                        value="{{ old('subject') }}"
                        placeholder="Asunto del mensaje"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        required
                    >
                    @error('subject')
                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email-body" class="mb-1.5 block text-xs font-medium text-gray-300">
                        Mensaje / Contenido <span class="text-[#c32720]">*</span>
                    </label>
                    <textarea
                        id="email-body"
                        name="body"
                        rows="13"
                        placeholder="Escribe aquí el mensaje completo..."
                        class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 font-sans text-xs leading-relaxed text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        required
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-2.5 border-t border-white/5 pt-3">
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Enviar correo
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Cancelar
                    </a>
                </div>
            </div>

            {{-- Panel secundario: Plantilla base y opciones de adjuntos --}}
            <aside class="space-y-4">
                <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white border-b border-white/5 pb-2">Opciones de redacción</h2>
                    <div>
                        <label for="template-selector" class="mb-1.5 block text-xs font-medium text-gray-300">
                            Cargar texto desde plantilla predefinida
                        </label>
                        <select
                            id="template-selector"
                            x-model="selectedTemplateId"
                            @change="applyTemplate()"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        >
                            <option value="">-- Seleccionar plantilla --</option>
                            <template x-for="tpl in templates" :key="tpl.id">
                                <option :value="tpl.id" x-text="tpl.name"></option>
                            </template>
                        </select>
                        <p class="mt-1.5 text-[11px] leading-relaxed text-gray-400">
                            Al elegir una plantilla, se rellenan el asunto y el mensaje para que puedas personalizarlos manualmente (por ejemplo, sustituyendo <code class="text-gray-300">{contact_person}</code> o <code class="text-gray-300">{band_name}</code>).
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-2.5 text-xs text-gray-300">
                    <h3 class="font-display text-xs uppercase tracking-wider text-white">Formato y archivos adjuntos</h3>
                    <p class="text-[11px] leading-relaxed text-gray-400">
                        Este formulario envía un correo directo en formato HTML (<code class="text-gray-300">DirectAdminEmail</code>) sin archivos adjuntos.
                    </p>
                    <p class="text-[11px] leading-relaxed text-gray-400">
                        Si necesitas adjuntar automáticamente el PDF oficial y el logotipo de la emisora, utiliza la sección <a href="{{ route('admin.media-kit.form') }}" class="text-[#ff8a84] underline hover:text-white">Dossier comercial</a>.
                    </p>
                </div>
            </aside>
        </form>
    </div>
</x-layouts.admin>
