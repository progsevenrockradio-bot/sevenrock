<x-layouts.admin title="Nueva campaña outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Nueva campaña</h1>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6" x-data="{
        selectedTemplate: @js(old('template_id', '')),
        recipientMode: @js(old('recipient_mode', 'contacts')),
        templateMap: @js($templates->mapWithKeys(fn ($template) => [$template->id => ['name' => $template->name, 'subject' => $template->subject, 'body' => $template->body]])),
        selectedCount: 0,
        toggleAll(checked) {
            document.querySelectorAll('[data-contact-checkbox]').forEach((el) => el.checked = checked);
            this.syncCount();
        },
        syncCount() {
            this.selectedCount = document.querySelectorAll('[data-contact-checkbox]:checked').length;
        }
    }" x-init="syncCount()">
        <form action="{{ route('admin.outreach.campaigns.store') }}" method="POST" class="space-y-6">
            @csrf
            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre</label>
                    <input name="name" value="{{ old('name') }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Plantilla</label>
                    <select name="template_id" class="lucille-product-field w-full" x-model="selectedTemplate">
                        <option value="">Selecciona una plantilla</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción</label>
                <textarea name="description" rows="4" class="lucille-product-field w-full">{{ old('description') }}</textarea>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Modo de envío</label>
                    <select name="recipient_mode" class="lucille-product-field w-full" x-model="recipientMode">
                        <option value="contacts" @selected(old('recipient_mode', 'contacts') === 'contacts')>Contactos seleccionados</option>
                        <option value="program" @selected(old('recipient_mode') === 'program')>Contactos de un programa</option>
                        <option value="producers" @selected(old('recipient_mode') === 'producers')>Productores de todos los programas</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Programa</label>
                    <select name="program_code" class="lucille-product-field w-full">
                        <option value="">Todos los programas</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_code }}" @selected(old('program_code') === $program->program_code)>{{ $program->program_code }} - {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Estado</label>
                    <select name="status_filter" class="lucille-product-field w-full">
                        <option value="">Todos</option>
                        @foreach (['pending','contacted','responded','registered','not_interested','invalid'] as $status)
                            <option value="{{ $status }}" @selected(old('status_filter') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Plantilla seleccionada</div>
                    <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]"><span x-text="selectedCount"></span> contactos seleccionados</div>
                </div>
                <div class="mt-3 text-sm text-[#d0d0d0]">
                    <template x-if="selectedTemplate && templateMap[selectedTemplate]">
                        <div>
                            <div class="text-[#f0f0f0]" x-text="templateMap[selectedTemplate].name"></div>
                            <div class="mt-2 text-[#9f9f9f]" x-text="templateMap[selectedTemplate].subject"></div>
                            <div class="mt-2 max-h-32 overflow-auto whitespace-pre-wrap text-[#7b7b7b]" x-text="templateMap[selectedTemplate].body"></div>
                        </div>
                    </template>
                    <p x-show="!selectedTemplate" class="text-[#7b7b7b]">Elige una plantilla para continuar.</p>
                </div>
            </div>

            <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Contactos</h2>
                    <label class="inline-flex items-center gap-2 text-sm text-[#d0d0d0]">
                        <input type="checkbox" @change="toggleAll($event.target.checked)">
                        Seleccionar todo
                    </label>
                </div>

                <div class="mt-4 max-h-[420px] overflow-auto border border-[#2b2b2b]">
                    <table class="w-full min-w-[960px] text-left text-sm">
                        <thead class="sticky top-0 bg-[#151515] text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                            <tr>
                                <th class="py-3 pl-4 pr-4">Sel</th>
                                <th class="py-3 pr-4">Banda</th>
                                <th class="py-3 pr-4">Programa</th>
                                <th class="py-3 pr-4">Email</th>
                                <th class="py-3 pr-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contacts as $contact)
                                <tr class="border-t border-[#242424]">
                                    <td class="py-3 pl-4 pr-4">
                                        <input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" data-contact-checkbox @change="syncCount()">
                                    </td>
                                    <td class="py-3 pr-4 text-[#dcdcdc]">{{ $contact->displayName() }}</td>
                                    <td class="py-3 pr-4 text-[#9f9f9f]">{{ $contact->programLabel() }}</td>
                                    <td class="py-3 pr-4 text-[#9f9f9f]">{{ $contact->email }}</td>
                                    <td class="py-3 pr-4 text-[#9f9f9f]">{{ $contact->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-[#7b7b7b]">No hay contactos con email disponibles.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="lucille-button-solid">Crear y enviar</button>
                <a href="{{ route('admin.outreach.campaigns.index') }}" class="lucille-button">Volver</a>
            </div>
        </form>
    </section>
</x-layouts.admin>
