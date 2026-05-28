<x-layouts.admin title="Nuevo contacto outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Nuevo contacto</h1>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <form action="{{ route('admin.outreach.contacts.store') }}" method="POST" class="grid gap-6 lg:grid-cols-2">
            @csrf
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Programa</label>
                <select name="program_code" class="lucille-product-field w-full">
                    <option value="">Sin programa</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->program_code }}" @selected(old('program_code') === $program->program_code)>{{ $program->program_code }} - {{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Origen</label>
                <select name="referral_source" class="lucille-product-field w-full">
                    @foreach (['producer' => 'Producer', 'self' => 'Self', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('referral_source', 'producer') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Radio artist ID</label>
                <input name="radio_artist_id" value="{{ old('radio_artist_id') }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                <input name="email" value="{{ old('email') }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Teléfono</label>
                <input name="phone" value="{{ old('phone') }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Persona de contacto</label>
                <input name="contact_person" value="{{ old('contact_person') }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Facebook</label>
                <input name="facebook" value="{{ old('facebook') }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Instagram</label>
                <input name="instagram" value="{{ old('instagram') }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Status</label>
                <select name="status" class="lucille-product-field w-full">
                    @foreach (['pending','contacted','responded','registered','not_interested','invalid'] as $status)
                        <option value="{{ $status }}" @selected(old('status', 'pending') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha límite</label>
                <input type="datetime-local" name="submission_deadline" value="{{ old('submission_deadline') }}" class="lucille-product-field w-full">
            </div>
            <div class="lg:col-span-2">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Notas</label>
                <textarea name="notes" rows="5" class="lucille-product-field w-full">{{ old('notes') }}</textarea>
            </div>
            <div class="lg:col-span-2 grid gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Material recibido</label>
                    <input type="datetime-local" name="materials_received_at" value="{{ old('materials_received_at') }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Backblaze path</label>
                    <input name="backblaze_path" value="{{ old('backblaze_path') }}" class="lucille-product-field w-full">
                </div>
            </div>
            <div class="lg:col-span-2 grid gap-6 md:grid-cols-2">
                <label class="inline-flex items-center gap-2 text-sm text-[#dcdcdc]">
                    <input type="checkbox" name="image_specs_met" value="1" @checked(old('image_specs_met'))>
                    Imágenes 1200x800 cumplidas
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-[#dcdcdc]">
                    <input type="checkbox" name="audio_specs_met" value="1" @checked(old('audio_specs_met'))>
                    Audio 192 kbps cumplido
                </label>
            </div>
            <div class="lg:col-span-2 flex flex-wrap gap-3">
                <button type="submit" class="lucille-button-solid">Guardar contacto</button>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button">Volver</a>
            </div>
        </form>
    </section>
</x-layouts.admin>
