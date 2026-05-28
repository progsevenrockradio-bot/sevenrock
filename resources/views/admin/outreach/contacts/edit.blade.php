<x-layouts.admin title="Editar contacto outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar contacto</h1>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <form action="{{ route('admin.outreach.contacts.update', $contact) }}" method="POST" class="grid gap-6 lg:grid-cols-2">
            @csrf
            @method('PUT')
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Radio artist ID</label>
                <input name="radio_artist_id" value="{{ old('radio_artist_id', $contact->radio_artist_id) }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                <input name="email" value="{{ old('email', $contact->email) }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Teléfono</label>
                <input name="phone" value="{{ old('phone', $contact->phone) }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Persona de contacto</label>
                <input name="contact_person" value="{{ old('contact_person', $contact->contact_person) }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Facebook</label>
                <input name="facebook" value="{{ old('facebook', $contact->facebook) }}" class="lucille-product-field w-full">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Instagram</label>
                <input name="instagram" value="{{ old('instagram', $contact->instagram) }}" class="lucille-product-field w-full">
            </div>
            <div class="lg:col-span-2">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Notas</label>
                <textarea name="notes" rows="5" class="lucille-product-field w-full">{{ old('notes', $contact->notes) }}</textarea>
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Status</label>
                <select name="status" class="lucille-product-field w-full">
                    @foreach (['pending','contacted','responded','registered','not_interested','invalid'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $contact->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button type="submit" class="lucille-button-solid">Guardar cambios</button>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button">Volver</a>
            </div>
        </form>
    </section>
</x-layouts.admin>
