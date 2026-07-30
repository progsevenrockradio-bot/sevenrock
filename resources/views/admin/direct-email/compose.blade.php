<x-layouts.admin title="Redactar Correo Directo">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Redactar Correo</h1>
                <p class="mt-1 text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Envía un correo directamente a cualquier destinatario usando o personalizando plantillas</p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-6 border border-emerald-600/40 bg-emerald-950/40 p-4 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-6 border border-rose-600/40 bg-rose-950/40 p-4 text-sm text-rose-300 space-y-1">
            @foreach ($errors->all() as $error)
                <p>• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6" x-data="{
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
        <form action="{{ route('admin.direct-email.send') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <label for="email-recipients" class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">
                        Para (Correos separados por coma) <span class="text-[var(--lucille-accent)]">*</span>
                    </label>
                    <input 
                        id="email-recipients" 
                        name="recipients" 
                        type="text"
                        value="{{ old('recipients', $defaultRecipient) }}" 
                        placeholder="ej. usuario@ejemplo.com, otro@ejemplo.com"
                        class="lucille-product-field w-full"
                        required
                    >
                </div>

                <div>
                    <label for="template-selector" class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">
                        Cargar desde plantilla predefinida
                    </label>
                    <select 
                        id="template-selector" 
                        x-model="selectedTemplateId" 
                        @change="applyTemplate()" 
                        class="lucille-product-field w-full"
                    >
                        <option value="">-- Seleccionar una plantilla --</option>
                        <template x-for="tpl in templates" :key="tpl.id">
                            <option :value="tpl.id" x-text="tpl.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div>
                <label for="email-subject" class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">
                    Asunto <span class="text-[var(--lucille-accent)]">*</span>
                </label>
                <input 
                    id="email-subject" 
                    name="subject" 
                    type="text"
                    value="{{ old('subject') }}" 
                    placeholder="Asunto del correo"
                    class="lucille-product-field w-full"
                    required
                >
            </div>

            <div>
                <label for="email-body" class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">
                    Mensaje / Contenido <span class="text-[var(--lucille-accent)]">*</span>
                </label>
                <textarea 
                    id="email-body" 
                    name="body" 
                    rows="14" 
                    placeholder="Escribe aquí el contenido del correo..."
                    class="lucille-product-field w-full font-mono text-sm leading-relaxed"
                    required
                >{{ old('body') }}</textarea>
                <p class="mt-2 text-xs text-[#9a9a9a]">
                    Nota: Puedes editar libremente el texto o reemplazar etiquetas genéricas (como {contact_person} o {band_name}) antes de realizar el envío.
                </p>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-[#2b2b2b]">
                <button type="submit" class="lucille-button-solid">
                    Enviar Correo
                </button>
                <a href="{{ route('admin.dashboard') }}" class="lucille-button">
                    Cancelar
                </a>
            </div>
        </form>
    </section>
</x-layouts.admin>
