<x-layouts.admin title="Nueva campaña de invitación">
    @php
        $statusLabels = [
            'pending' => 'Pendiente',
            'contacted' => 'Contactada',
            'responded' => 'Con respuesta',
            'registered' => 'Registrada',
            'not_interested' => 'Sin interés',
            'invalid' => 'Correo inválido',
        ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans" x-data="{
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
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Contactos e invitaciones · Preparación de envío</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Nueva campaña de invitación</h1>
                <p class="mt-1 text-xs text-gray-400">Selecciona la plantilla, el alcance de destinatarios y verifica la vista previa antes de poner el envío en cola.</p>
            </div>
            <a href="{{ route('admin.outreach.campaigns.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                Volver a campañas
            </a>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <p class="font-semibold">Revisa los campos señalados:</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('admin.outreach.campaigns.store') }}"
            method="POST"
            class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5"
            data-confirm="¿Confirmas crear esta campaña de invitación y encolar sus envíos?"
            data-confirm-title="Encolar campaña de invitación"
            data-confirm-action="Crear y enviar a cola"
            data-confirm-tone="soft"
        >
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="campaign-name" class="mb-1.5 block text-xs font-medium text-gray-300">Nombre de la campaña *</label>
                    <input id="campaign-name" name="name" value="{{ old('name') }}" required placeholder="Ej: Convocatoria bandas octubre" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="campaign-template" class="mb-1.5 block text-xs font-medium text-gray-300">Plantilla de invitación *</label>
                    <select id="campaign-template" name="template_id" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" x-model="selectedTemplate">
                        <option value="">Selecciona una plantilla activa</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="campaign-desc" class="mb-1.5 block text-xs font-medium text-gray-300">Descripción u objetivo (opcional)</label>
                <textarea id="campaign-desc" name="description" rows="2" class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('description') }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="campaign-mode" class="mb-1.5 block text-xs font-medium text-gray-300">Alcance de destinatarios</label>
                    <select id="campaign-mode" name="recipient_mode" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]" x-model="recipientMode">
                        <option value="contacts" @selected(old('recipient_mode', 'contacts') === 'contacts')>Contactos seleccionados manualmente</option>
                        <option value="program" @selected(old('recipient_mode') === 'program')>Todos los contactos de un programa</option>
                        <option value="producers" @selected(old('recipient_mode') === 'producers')>Productores de todos los programas</option>
                    </select>
                </div>
                <div>
                    <label for="campaign-program" class="mb-1.5 block text-xs font-medium text-gray-300">Filtrar por programa</label>
                    <select id="campaign-program" name="program_code" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        <option value="">Todos los programas</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_code }}" @selected(old('program_code') === $program->program_code)>{{ $program->program_code }} - {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="campaign-status" class="mb-1.5 block text-xs font-medium text-gray-300">Filtrar por estado</label>
                    <select id="campaign-status" name="status_filter" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        <option value="">Todos los estados</option>
                        @foreach ($statusLabels as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(old('status_filter') === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Resumen de plantilla seleccionada --}}
            <div class="rounded-lg border border-white/10 bg-[#151518] p-3.5 text-xs">
                <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-2">
                    <span class="font-semibold text-gray-300">Vista previa de la plantilla seleccionada</span>
                    <span class="text-[11px] text-gray-400"><strong class="text-white" x-text="selectedCount"></strong> contactos marcados</span>
                </div>
                <div class="mt-2.5">
                    <template x-if="selectedTemplate && templateMap[selectedTemplate]">
                        <div class="space-y-1.5">
                            <div class="font-semibold text-white" x-text="templateMap[selectedTemplate].name"></div>
                            <div class="text-gray-300">Asunto: <span class="text-white" x-text="templateMap[selectedTemplate].subject"></span></div>
                            <div class="max-h-28 overflow-y-auto rounded border border-white/5 bg-[#101012] p-2.5 whitespace-pre-wrap text-[11px] text-gray-400" x-text="templateMap[selectedTemplate].body"></div>
                        </div>
                    </template>
                    <p x-show="!selectedTemplate" class="text-gray-400">Selecciona una plantilla arriba para ver su asunto y contenido base.</p>
                </div>
            </div>

            {{-- Selector de contactos --}}
            <div class="rounded-lg border border-white/10 bg-[#151518] p-3.5 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-xs uppercase tracking-wider text-white">Contactos con correo disponible ({{ $contacts->count() }})</h2>
                    <label class="inline-flex items-center gap-2 text-xs text-gray-300 cursor-pointer">
                        <input type="checkbox" @change="toggleAll($event.target.checked)" class="h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]">
                        <span>Seleccionar todos</span>
                    </label>
                </div>

                <div class="max-h-[340px] overflow-y-auto rounded-lg border border-white/5 bg-[#101012]">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-[#151518] text-[11px] font-semibold text-gray-400 border-b border-white/10">
                            <tr>
                                <th class="px-3 py-2.5 w-10">Sel.</th>
                                <th class="px-3 py-2.5">Banda</th>
                                <th class="px-3 py-2.5">Programa</th>
                                <th class="px-3 py-2.5">Correo</th>
                                <th class="px-3 py-2.5">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse ($contacts as $contact)
                                <tr class="hover:bg-white/[0.02]">
                                    <td class="px-3 py-2.5">
                                        <input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" data-contact-checkbox @change="syncCount()" class="h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]">
                                    </td>
                                    <td class="px-3 py-2.5 font-medium text-white">{{ $contact->displayName() }}</td>
                                    <td class="px-3 py-2.5 text-gray-400">{{ $contact->programLabel() }}</td>
                                    <td class="px-3 py-2.5 text-gray-300">{{ $contact->email }}</td>
                                    <td class="px-3 py-2.5 text-gray-400">{{ $statusLabels[$contact->status] ?? $contact->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">No hay contactos con correo electrónico disponible.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 border-t border-white/5 pt-4">
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Crear y encolar campaña
                </button>
                <a href="{{ route('admin.outreach.campaigns.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.admin>
