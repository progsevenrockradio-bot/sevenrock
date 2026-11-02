<x-layouts.admin title="Editar contacto de banda">
    @php
        $statusLabels = [
            'pending' => 'Pendiente',
            'contacted' => 'Contactada',
            'responded' => 'Con respuesta',
            'registered' => 'Registrada',
            'not_interested' => 'Sin interés',
            'invalid' => 'Correo inválido',
        ];
        $sourceLabels = [
            'producer' => 'Productor / Programa',
            'self' => 'Solicitud propia',
            'other' => 'Otro origen',
        ];
    @endphp

    <div class="mx-auto max-w-5xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Contactos e invitaciones · Edición</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Editar contacto: {{ $contact->displayName() }}</h1>
                <p class="mt-1 text-xs text-gray-400">Actualiza datos de contacto, programa vinculado y verificación de materiales.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    Ver ficha
                </a>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    Volver a contactos
                </a>
            </div>
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

        <form action="{{ route('admin.outreach.contacts.update', $contact) }}" method="POST" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
            @csrf
            @method('PUT')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="contact-program" class="mb-1.5 block text-xs font-medium text-gray-300">Programa asignado</label>
                    <select id="contact-program" name="program_code" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        <option value="">Sin programa</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_code }}" @selected(old('program_code', $contact->program_code) === $program->program_code)>{{ $program->program_code }} - {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="contact-source" class="mb-1.5 block text-xs font-medium text-gray-300">Origen del contacto</label>
                    <select id="contact-source" name="referral_source" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        @foreach ($sourceLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('referral_source', $contact->referral_source ?: 'producer') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="contact-artist-id" class="mb-1.5 block text-xs font-medium text-gray-300">ID de perfil de artista</label>
                    <input id="contact-artist-id" name="radio_artist_id" value="{{ old('radio_artist_id', $contact->radio_artist_id) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-email" class="mb-1.5 block text-xs font-medium text-gray-300">Correo electrónico</label>
                    <input id="contact-email" type="email" name="email" value="{{ old('email', $contact->email) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-person" class="mb-1.5 block text-xs font-medium text-gray-300">Persona de contacto</label>
                    <input id="contact-person" name="contact_person" value="{{ old('contact_person', $contact->contact_person) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-phone" class="mb-1.5 block text-xs font-medium text-gray-300">Teléfono</label>
                    <input id="contact-phone" name="phone" value="{{ old('phone', $contact->phone) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-facebook" class="mb-1.5 block text-xs font-medium text-gray-300">Enlace de Facebook</label>
                    <input id="contact-facebook" name="facebook" value="{{ old('facebook', $contact->facebook) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-instagram" class="mb-1.5 block text-xs font-medium text-gray-300">Enlace de Instagram</label>
                    <input id="contact-instagram" name="instagram" value="{{ old('instagram', $contact->instagram) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-status" class="mb-1.5 block text-xs font-medium text-gray-300">Estado de seguimiento</label>
                    <select id="contact-status" name="status" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        @foreach ($statusLabels as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(old('status', $contact->status) === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="contact-deadline" class="mb-1.5 block text-xs font-medium text-gray-300">Fecha límite de entrega</label>
                    <input id="contact-deadline" type="datetime-local" name="submission_deadline" value="{{ old('submission_deadline', optional($contact->submission_deadline)->format('Y-m-d\TH:i')) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-received" class="mb-1.5 block text-xs font-medium text-gray-300">Fecha de recepción de material</label>
                    <input id="contact-received" type="datetime-local" name="materials_received_at" value="{{ old('materials_received_at', optional($contact->materials_received_at)->format('Y-m-d\TH:i')) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
                <div>
                    <label for="contact-b2" class="mb-1.5 block text-xs font-medium text-gray-300">Ruta de almacenamiento</label>
                    <input id="contact-b2" name="backblaze_path" value="{{ old('backblaze_path', $contact->backblaze_path) }}" class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
            </div>

            <div>
                <label for="contact-notes" class="mb-1.5 block text-xs font-medium text-gray-300">Notas de seguimiento</label>
                <textarea id="contact-notes" name="notes" rows="4" class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('notes', $contact->notes) }}</textarea>
            </div>

            <div>
                <label for="contact-materials-note" class="mb-1.5 block text-xs font-medium text-gray-300">Notas técnicas del material</label>
                <textarea id="contact-materials-note" name="materials_note" rows="3" class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('materials_note', $contact->materials_note) }}</textarea>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex items-center gap-2.5 rounded-lg border border-white/5 bg-[#151518] px-3 py-2.5 text-xs text-gray-200 cursor-pointer">
                    <input type="checkbox" name="image_specs_met" value="1" @checked(old('image_specs_met', $contact->image_specs_met)) class="h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]">
                    <span>Especificaciones de imagen cumplidas (1200x800 px)</span>
                </label>
                <label class="flex items-center gap-2.5 rounded-lg border border-white/5 bg-[#151518] px-3 py-2.5 text-xs text-gray-200 cursor-pointer">
                    <input type="checkbox" name="audio_specs_met" value="1" @checked(old('audio_specs_met', $contact->audio_specs_met)) class="h-4 w-4 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]">
                    <span>Especificaciones de audio cumplidas (MP3 192 kbps)</span>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 border-t border-white/5 pt-4">
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Guardar cambios
                </button>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.admin>
