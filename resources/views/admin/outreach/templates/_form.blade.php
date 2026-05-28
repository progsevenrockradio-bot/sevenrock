@php
    $csrf = csrf_token();
    $placeholderGroups = collect($availableVariables)->groupBy(function (array $item): string {
        return match (true) {
            in_array($item['key'], ['{band_name}', '{band_genre}', '{band_country}', '{contact_person}'], true) => 'Banda',
            in_array($item['key'], ['{program_code}', '{program_name}', '{producer_name}'], true) => 'Programa',
            in_array($item['key'], ['{image_specs}', '{audio_specs}', '{submission_days}', '{launch_date}'], true) => 'Specs y fechas',
            default => 'General',
        };
    });
@endphp

<section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6" x-data="{
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
    <form action="{{ $action }}" method="POST" class="space-y-6">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre</label>
                <input name="name" value="{{ old('name', $template->name) }}" class="lucille-product-field w-full">
            </div>
            <div class="flex items-end gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-[#dcdcdc]">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))>
                    Activa
                </label>
                <button type="button" class="lucille-button" @click="preview()">Vista previa con datos reales</button>
            </div>
        </div>

        <div>
            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Asunto</label>
            <input id="outreach-subject" name="subject" value="{{ old('subject', $template->subject) }}" class="lucille-product-field w-full">
        </div>

        <div>
            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cuerpo HTML</label>
            <textarea id="outreach-body" name="body" rows="12" class="lucille-product-field w-full">{{ old('body', $template->body) }}</textarea>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_.85fr]">
            <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Variables disponibles</div>
                <div class="mt-4 space-y-4">
                    @foreach ($placeholderGroups as $groupName => $groupItems)
                        <div>
                            <div class="text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">{{ $groupName }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($groupItems as $variable)
                                    <button type="button" class="lucille-button text-xs" @click="insertAtCursor('outreach-body', '{{ $variable['key'] }}')">{{ $variable['key'] }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border border-[#2b2b2b] bg-[#151515] p-4 space-y-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Programa para preview</label>
                    <select class="lucille-product-field w-full" x-model="previewProgramId">
                        <option value="">Sin programa</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->program_code }} - {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Banda para preview</label>
                    <select class="lucille-product-field w-full" x-model="previewContactId">
                        <option value="">Contacto demo</option>
                        @foreach ($sampleContacts as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Preview rápido</div>
                <p class="text-sm leading-6 text-[#9f9f9f]">
                    El render de prueba usa el programa y la banda seleccionados para validar placeholders antes de enviar.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="lucille-button-solid">{{ $buttonLabel }}</button>
            <a href="{{ route('admin.outreach.templates.index') }}" class="lucille-button">Volver</a>
        </div>
    </form>

    <div x-show="previewOpen" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center bg-[rgba(0,0,0,.78)] px-4">
        <div class="w-full max-w-3xl border border-[#2b2b2b] bg-[#111] p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Vista previa</h2>
                <button type="button" class="lucille-button" @click="previewOpen = false">Cerrar</button>
            </div>
            <div class="mt-5 space-y-4 text-sm text-[#d0d0d0]">
                <div>
                    <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Asunto</div>
                    <div class="mt-1 text-[#f0f0f0]" x-text="previewSubject"></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cuerpo</div>
                    <div class="mt-1 whitespace-pre-wrap rounded border border-[#2b2b2b] bg-[#151515] p-4 leading-7" x-html="previewBody"></div>
                </div>
            </div>
        </div>
    </div>
</section>
