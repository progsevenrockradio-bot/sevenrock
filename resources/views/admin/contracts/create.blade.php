@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Crear Contrato - '.$themeSettings->site_name">
    @if ($errors->any())
        <div class="mb-6 border border-[#c32720]/20 bg-[#c32720]/5 px-4 py-3 text-sm text-[#ff7875]">
            <strong class="font-display uppercase tracking-[.08em] block mb-2">Se encontraron errores:</strong>
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Nuevo Contrato Digital</h1>
        <p class="mt-2 text-[#7b7b7b]">Crea un contrato para firma electrónica. Al guardar, se enviará el enlace de firma automáticamente por correo.</p>
    </div>

    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-[8px]">
        <form action="{{ route('admin.contracts.store') }}" method="POST" x-data="contractTemplateCreator()" x-init="init()">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b] font-semibold">Seleccionar Plantilla de Contrato</label>
                    <select @change="changeTemplate($event.target.value)" class="lucille-product-field w-full">
                        @foreach($templates as $key => $tpl)
                            <option value="{{ $key }}">{{ $tpl['name'] }}</option>
                        @endforeach
                        <option value="custom">Personalizado / Vacío</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre del Firmante / Representante Legal</label>
                    <input name="signer_name" value="{{ old('signer_name') }}" class="lucille-product-field w-full" placeholder="Ej. Juan Pérez" required>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo Electrónico del Firmante</label>
                    <input type="email" name="signer_email" value="{{ old('signer_email') }}" class="lucille-product-field w-full" placeholder="Ej. juan@correo.com" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre de la Banda / Artista</label>
                    <input name="band_name" value="{{ old('band_name') }}" class="lucille-product-field w-full" placeholder="Ej. Foo Fighters / Artista Solista" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título del Contrato</label>
                    <input name="title" x-model="title" class="lucille-product-field w-full" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cuerpo del Contrato (Cláusulas)</label>
                    <p class="mb-2 text-xs text-gray-500 italic">* Puedes editar este texto para personalizarlo según el acuerdo.</p>
                    <textarea name="content" x-model="content" rows="15" class="lucille-product-field w-full font-mono text-sm leading-relaxed" required></textarea>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <button type="submit" class="lucille-button-solid">Crear y Enviar Contrato</button>
                <a href="{{ route('admin.contracts.index') }}" class="lucille-button">Cancelar</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function contractTemplateCreator() {
            return {
                title: '',
                content: '',
                templates: @js($templates),
                init() {
                    this.templates['custom'] = { title: '', body: '' };
                    this.title = @js(old('title', 'Contrato de Alquiler de Espacio Digital y Difusión - Plan FREE (Gratuito)'));
                    this.content = @js(old('content', $defaultTemplate));
                },
                changeTemplate(val) {
                    if (this.templates[val]) {
                        this.title = this.templates[val].title;
                        this.content = this.templates[val].body;
                    }
                }
            };
        }
    </script>
    @endpush
</x-layouts.admin>
