@php
    $csrf = csrf_token();
    $placeholderGroups = collect($availableVariables)->groupBy(function (array $item): string {
        return match (true) {
            in_array($item['key'], ['{band_name}', '{band_genre}', '{band_country}', '{contact_person}'], true) => 'Datos de la banda',
            in_array($item['key'], ['{program_code}', '{program_name}', '{producer_name}'], true) => 'Datos del programa',
            in_array($item['key'], ['{image_specs}', '{audio_specs}', '{submission_days}', '{launch_date}'], true) => 'Requisitos y fechas',
            default => 'General',
        };
    });
@endphp

<section class="rounded-xl border border-white/10 bg-[#101012] p-5" x-data="{
    previewOpen: false,
    previewSubject: '',
    previewBody: '',
    previewProgramId: '{{ old('preview_program_id', $programs->first()?->id ?? '') }}',
    previewContactId: '{{ old('preview_contact_id', $sampleContacts->first()?->id ?? '') }}',
    insertAtCursor(field, value) {
        const el = document.getElementById(field);
        if (!el) return;
        const start = el.selectionStart ?? el.value.length;
        const end = el.selectionEnd ?? el.value.length;
        el.value = el.value.slice(0, start) + value + el.value.slice(end);
        el.focus();
        const caret = start + value.length;
        el.setSelectionRange(caret, caret);
    },
    async preview() {
        const response = await fetch(@json(route('admin.outreach.templates.preview')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @json($csrf),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                subject: document.getElementById('outreach-subject').value,
                body: document.getElementById('outreach-body').value,
                program_id: this.previewProgramId || null,
                contact_id: this.previewContactId || null,
            }),
        });
        const payload = await response.json();
        this.previewSubject = payload.subject ?? '';
        this.previewBody = payload.body ?? '';
        this.previewOpen = true;
    },
}">
    <form action="{{ $action }}" method="POST" class="space-y-5">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <div class="grid gap-4 lg:grid-cols-[1fr_auto] items-end">
            <div>
                <label for="outreach-name" class="mb-1.5 block text-xs font-medium text-gray-300">Nombre de la plantilla *</label>
                <input id="outreach-name" name="name" value="{{ old('name', $template->name) }}" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-200 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]">
                    <span>Plantilla activa</span>
                </label>
                <button type="button" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white" @click="preview()">
                    Vista previa con datos reales
                </button>
            </div>
        </div>

        <div>
            <label for="outreach-subject" class="mb-1.5 block text-xs font-medium text-gray-300">Asunto del correo *</label>
            <input id="outreach-subject" name="subject" value="{{ old('subject', $template->subject) }}" required class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
        </div>

        <div>
            <label for="outreach-body" class="mb-1.5 block text-xs font-medium text-gray-300">Cuerpo del mensaje (HTML / Texto) *</label>
            <textarea id="outreach-body" name="body" rows="12" required class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 font-mono text-xs leading-relaxed text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('body', $template->body) }}</textarea>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.15fr_.85fr]">
            <div class="rounded-lg border border-white/5 bg-[#151518] p-4 space-y-3">
                <div class="text-xs font-semibold text-white">Variables disponibles (clic para insertar en el cuerpo)</div>
                <div class="space-y-3">
                    @foreach ($placeholderGroups as $groupName => $groupItems)
                        <div>
                            <div class="text-[11px] font-medium text-gray-400">{{ $groupName }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach ($groupItems as $variable)
                                    <button type="button" class="inline-flex items-center rounded border border-white/10 bg-[#101012] px-2 py-1 font-mono text-[11px] text-gray-200 hover:border-[#c32720] hover:text-white" @click="insertAtCursor('outreach-body', '{{ $variable['key'] }}')">
                                        {{ $variable['key'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-white/5 bg-[#151518] p-4 space-y-3">
                <div class="text-xs font-semibold text-white">Parámetros para la vista previa</div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-400">Programa de ejemplo</label>
                    <select class="h-9 w-full rounded-lg border border-white/10 bg-[#101012] px-3 text-xs text-white" x-model="previewProgramId">
                        <option value="">Sin programa</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->program_code }} - {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-400">Contacto de banda de ejemplo</label>
                    <select class="h-9 w-full rounded-lg border border-white/10 bg-[#101012] px-3 text-xs text-white" x-model="previewContactId">
                        <option value="">Contacto demo</option>
                        @foreach ($sampleContacts as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-[11px] leading-relaxed text-gray-400">
                    La vista previa evalúa los marcadores con el programa y la banda elegidos sin enviar ningún correo real.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 border-t border-white/5 pt-4">
            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                {{ $buttonLabel }}
            </button>
            <a href="{{ route('admin.outreach.templates.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                Cancelar
            </a>
        </div>
    </form>

    <div x-show="previewOpen" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center bg-black/80 px-4 backdrop-blur-sm">
        <div class="w-full max-w-2xl rounded-xl border border-white/10 bg-[#101012] p-5 shadow-2xl space-y-4" @click.away="previewOpen = false">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
                <h2 class="font-display text-base uppercase tracking-wider text-white">Vista previa del mensaje</h2>
                <button type="button" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs text-gray-300 hover:text-white" @click="previewOpen = false">
                    Cerrar
                </button>
            </div>
            <div class="space-y-3 text-xs">
                <div>
                    <div class="text-[11px] font-medium text-gray-400">Asunto renderizado</div>
                    <div class="mt-1 font-semibold text-white" x-text="previewSubject"></div>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-gray-400">Cuerpo renderizado</div>
                    <div class="mt-1 max-h-96 overflow-y-auto whitespace-pre-wrap rounded-lg border border-white/10 bg-[#151518] p-3.5 leading-relaxed text-gray-200" x-html="previewBody"></div>
                </div>
            </div>
        </div>
    </div>
</section>
